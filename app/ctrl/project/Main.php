<?php
/**
 * Created by PhpStorm.
 */

namespace main\app\ctrl\project;

use main\app\classes\ConfigLogic;
use main\app\classes\IssueLogic;
use main\app\classes\LogOperatingLogic;
use main\app\classes\PermissionGlobal;
use main\app\classes\PermissionLogic;
use main\app\classes\ProjectGantt;
use main\app\classes\UserAuth;
use main\app\classes\UserLogic;
use main\app\classes\IssueFilterLogic;
use main\app\ctrl\Agile;
use main\app\ctrl\project\Mind;
use main\app\ctrl\BaseCtrl;
use main\app\ctrl\issue\Main as IssueMain;
use main\app\event\CommonPlacedEvent;
use main\app\event\Events;
use main\app\model\field\FieldModel;
use main\app\model\issue\IssueTypeModel;
use main\app\model\issue\IssueTypeSchemeModel;
use main\app\model\issue\WorkflowSchemeModel;
use main\app\model\OrgModel;
use main\app\model\ActivityModel;
use main\app\model\project\ProjectFlagModel;
use main\app\model\project\ProjectIssueTypeSchemeDataModel;
use main\app\model\project\ProjectLabelModel;
use main\app\model\project\ProjectMainExtraModel;
use main\app\model\project\ProjectModel;
use main\app\model\agile\SprintModel;
use main\app\model\project\ProjectModuleModel;
use main\app\classes\SettingsLogic;
use main\app\classes\ProjectLogic;
use main\app\classes\RewriteUrl;
use main\app\model\ProjectTemplateDisplayCategoryModel;
use main\app\model\ProjectTemplateModel;
use main\app\model\user\UserModel;

/**
 * 项目
 */
class Main extends Base
{
    /**
     * Main constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        parent::addGVar('top_menu_active', 'project');
        parent::addGVar('projects', ConfigLogic::getJoinProjects());
    }

    public function pageIndex()
    {
    }

    /**
     * 创建项目页面
     * @throws \Exception
     */
    public function pageNew()
    {
        $userId = UserAuth::getId();
        if (!PermissionGlobal::check($userId, PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限！');
            exit;
        }

        $orgModel = new OrgModel();
        $orgList = $orgModel->getAllItems();

        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();
        $data = [];
        $data['title'] = '创建项目';
        $data['sub_nav_active'] = 'project';
        $data['users'] = $users;

        $data['org_list'] = $orgList;
        $data['project_name_max_length'] = (new SettingsLogic)->maxLengthProjectName();
        $data['project_key_max_length'] = (new SettingsLogic)->maxLengthProjectKey();
        $data['project_tpl_group_arr'] = ProjectLogic::getProjectTplByCategory();

        $data['root_domain'] = ROOT_URL;

        $this->render('gitlab/project/main_form.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageHome()
    {
        $data = [];

        $data['nav_links_active'] = 'home';
        $data['sub_nav_active'] = 'profile';
        $data['scrolling_tabs'] = 'home';
        $data = RewriteUrl::setProjectData($data);
        $data['title'] = $data['project_name'];
        // 权限判断
        if (!empty($data['project_id'])) {
            $data['issue_main_url'] = ROOT_URL . substr($data['project_root_url'], 1) . '/issues';
            if (!$this->isAdmin && !PermissionLogic::checkUserHaveProjectItem(UserAuth::getId(), $data['project_id'])) {
                $this->warn('提 示', '您没有权限访问该项目,请联系管理员申请加入该项目');
                die;
            }
        }

        $projectMainExtraModel = new ProjectMainExtraModel();
        $projectExtraInfo = $projectMainExtraModel->getByProjectId($data['project_id']);

        if (empty($projectExtraInfo)) {
            $data['project']['detail'] = '';
        } else {
            $data['project']['detail'] = $projectExtraInfo['detail'];
        }

        $userLogic = new UserLogic();
        $userList = $userLogic->getUsersAndRoleByProjectId($data['project_id']);
        $data['members'] = $userList;

        $this->render('gitlab/project/home.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageProfile()
    {
        $this->pageHome();
    }

    /**
     * @throws \Exception
     */
    public function pageIssueType()
    {
        $data = [];
        $data['nav_links_active'] = 'home';
        $data['sub_nav_active'] = 'issue_type';
        $data['scrolling_tabs'] = 'home';
        $data = RewriteUrl::setProjectData($data);

        // 权限判断
        if (!empty($data['project_id'])) {
            if (!$this->isAdmin && !PermissionLogic::checkUserHaveProjectItem(UserAuth::getId(), $data['project_id'])) {
                $this->warn('提 示', '您没有权限访问该项目,请联系管理员申请加入该项目');
                die;
            }
        }
        $projectLogic = new ProjectLogic();
        $list = $projectLogic->typeList($data['project_id']);
        $data['title'] = '事项类型 - ' . $data['project_name'];
        $data['list'] = $list;

        $this->render('gitlab/project/issue_type.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageVersion()
    {
        $projectModel = new ProjectModel();
        $projectName = $projectModel->getNameById($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $data = [];
        $data['title'] = '版本 - ' . $projectName['name'];
        $data['nav_links_active'] = 'home';
        $data['sub_nav_active'] = 'version';
        $data['scrolling_tabs'] = 'home';

        $data['query_str'] = http_build_query($_GET);
        $data = RewriteUrl::setProjectData($data);

        $this->render('gitlab/project/version.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageModule()
    {
        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();

        $projectModuleModel = new ProjectModuleModel();
        $count = $projectModuleModel->getAllCount($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $projectModel = new ProjectModel();
        $projectName = $projectModel->getNameById($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $data = [];
        $data['title'] = '模块 - ' . $projectName['name'];
        $data['nav_links_active'] = 'home';
        $data['sub_nav_active'] = 'module';
        $data['users'] = $users;
        $data['query_str'] = http_build_query($_GET);
        $data['count'] = $count;

        $data = RewriteUrl::setProjectData($data);

        $this->render('gitlab/project/module.php', $data);
    }

    /**
     * 跳转至事项页面
     * @throws \Exception
     */
    public function pageIssues()
    {
        $issueMainCtrl = new IssueMain();
        $issueMainCtrl->pageIndex();
    }

    /**
     * 跳转至事项页面
     * @throws \Exception
     */
    public function pagePlugin()
    {
        // 1.取出插件名称
        $pluginName = $_GET['_target'][3];
        $pluginFile = PLUGIN_PATH . $pluginName . "/index.php";
        //var_dump($pluginFile);
        if (file_exists($pluginFile)) {
            require_once($pluginFile);
            $pluginIndexClass = sprintf("main\\plugin\\%s\\%s", $pluginName, 'Index');
            if (class_exists($pluginIndexClass)) {
                $indexCtrl = new $pluginIndexClass($this->dispatcher);
                if (method_exists($indexCtrl, 'main')) {
                    $indexCtrl->main();
                }
                if (method_exists($indexCtrl, 'pageIndex')) {
                    $indexCtrl->pageIndex();
                }
            } else {
                echo "入口类: {$pluginIndexClass} 缺失";
            }

        } else {
            echo "入口文件: {$pluginFile} 缺失";
        }
    }

    /**
     * backlog页面
     * @throws \Exception
     */
    public function pageMind()
    {
        $ctrl = new Mind();
        $ctrl->pageIndex();
    }

    /**
     * backlog页面
     * @throws \Exception
     */
    public function pageBacklog()
    {
        $agileCtrl = new Agile();
        $agileCtrl->pageBacklog();
    }

    /**
     * Sprints页面
     * @throws \Exception
     */
    public function pageSprints()
    {
        $agileCtrl = new Agile();
        $agileCtrl->pageSprint();
    }

    /**
     * Kanban页面
     * @throws \Exception
     */
    public function pageKanban()
    {
        $agileCtrl = new Agile();
        $agileCtrl->pageBoard();
    }

    /**
     * 设置页面
     * @throws \Exception
     */
    public function pageSettings()
    {
        $this->pageSettingsProfile();
    }

    /**
     * @throws \Exception
     */
    public function pageChart()
    {
        $chartCtrl = new Chart();
        $chartCtrl->pageProject();
    }

    /**
     * @throws \Exception
     */
    public function pageChartSprint()
    {
        $chartCtrl = new Chart();
        $chartCtrl->pageSprint();
    }


    /**
     * @throws \Exception
     * @todo 此处有bug, 不能即是页面有时ajax的处理
     */
    public function pageSettingsProfile()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $projectModel = new ProjectModel();
        $info = $projectModel->getById($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $projectMainExtra = new ProjectMainExtraModel();
        $infoExtra = $projectMainExtra->getByProjectId($info['id']);
        if ($infoExtra) {
            $info['detail'] = $infoExtra['detail'];
        } else {
            $info['detail'] = '';
        }

        $orgModel = new OrgModel();
        $orgList = $orgModel->getAllItems();
        $data['org_list'] = $orgList;

        $orgName = $orgModel->getField('name', array('id' => $info['org_id']));
        $data['title'] = '设置';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'basic_info';

        //$data['users'] = $users;
        $info['org_name'] = $orgName;
        $projectTpl = (new ProjectTemplateModel())->getById($info['project_tpl_id']);
        if($projectTpl){
            $info['project_tpl_text'] = $projectTpl['name'];
        }
        $data['info'] = $info;

        $data['root_domain'] = ROOT_URL;

        // 事项类型方案
        $projectIssueTypeSchemeDataModel = new ProjectIssueTypeSchemeDataModel();
        $data['issue_type_scheme_id'] = $projectIssueTypeSchemeDataModel->getSchemeId($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        // 事项类型方案列表
        $issueTypeSchemeModel = new IssueTypeSchemeModel();
        $data['issue_type_schemes'] = $issueTypeSchemeModel->getAll();

        // 状态流方案列表
        $workflowSchemeModel = new WorkflowSchemeModel();
        $data['workflow_schemes'] = $workflowSchemeModel->getAll();

        // 事项类型
        $data['issueTypeArr'] = (new IssueTypeModel())->getAllItems(false);

        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();
        $data['users'] = $users;

        $data = RewriteUrl::setProjectData($data);

        $this->render('gitlab/project/setting_basic_info.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsDisplayField()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }
        $projectModel = new ProjectModel();
        $data['title'] = '设置';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'display_field';

        $data['root_domain'] = ROOT_URL;
        $data = RewriteUrl::setProjectData($data);

        // 表格视图的显示字段
        $issueLogic = new IssueLogic();
        $data['display_fields'] = $issueLogic->fetchProjectDisplayFields($data['project_id']);

        $uiDisplayFields = IssueLogic::$uiDisplayFields;
        $fieldsArr = FieldModel::getInstance()->getCustomFields();
        $fieldsIdArr = array_column($fieldsArr, 'title', 'name');
        $data['uiDisplayFields'] = array_merge($uiDisplayFields, $fieldsIdArr);

        $projectFlagModel = new ProjectFlagModel();
        $isUserDisplayField = $projectFlagModel->getValueByFlag($data['project_id'], "is_user_display_field");
        if(is_null($isUserDisplayField)){
            $data['is_user_display_field'] = "1";
        }else{
            $data['is_user_display_field'] = $isUserDisplayField;
        }
        $this->render('gitlab/project/setting_display_field.twig', $data);
    }


    /**
     * @throws \Exception
     */
    public function pageSettingsFilter()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }
        $data['title'] = '设置';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'filter';
        $data['root_domain'] = ROOT_URL;
        $data = RewriteUrl::setProjectData($data);
        //print_r($data['project']);die;
        $this->render('gitlab/project/setting_filter.twig', $data);
    }


    /**
     * @throws \Exception
     * @todo 此处有bug, 不能即是页面有时ajax的处理
     */
    public function pageSettingIssue()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $projectModel = new ProjectModel();
        $info = $projectModel->getById($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $projectMainExtra = new ProjectMainExtraModel();
        $infoExtra = $projectMainExtra->getByProjectId($info['id']);
        if ($infoExtra) {
            $info['detail'] = $infoExtra['detail'];
        } else {
            $info['detail'] = '';
        }

        $orgModel = new OrgModel();
        $orgList = $orgModel->getAllItems();
        $data['org_list'] = $orgList;

        $orgName = $orgModel->getField('name', array('id' => $info['org_id']));
        $data['title'] = '设置';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'issue_setting';

        //$data['users'] = $users;
        $info['org_name'] = $orgName;
        $projectTpl = (new ProjectTemplateModel())->getById($info['project_tpl_id']);
        if($projectTpl){
            $info['project_tpl_text'] = $projectTpl['name'];
        }
        $data['info'] = $info;

        $data['root_domain'] = ROOT_URL;

        // 事项类型方案
        $projectIssueTypeSchemeDataModel = new ProjectIssueTypeSchemeDataModel();
        $data['issue_type_scheme_id'] = $projectIssueTypeSchemeDataModel->getSchemeId($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        // 事项类型方案列表
        $issueTypeSchemeModel = new IssueTypeSchemeModel();
        $data['issue_type_schemes'] = $issueTypeSchemeModel->getAll();

        // 状态流方案列表
        $workflowSchemeModel = new WorkflowSchemeModel();
        $data['workflow_schemes'] = $workflowSchemeModel->getAll();

        // 事项类型
        $data['issueTypeArr'] = (new IssueTypeModel())->getAllItems(false);

        $data['rememberFieldArr'] = [
           // 'issue_type'=>'事项类型',  // 因为与"默认事项类型"功能相冲突，新版本3.2.0 取消此选项
            'module'=>'模 块',
            'assignee'=>'处理人',
            'fix_version'=>'解决版本',
            'labels'=>'标 签',
        ];

        $data = RewriteUrl::setProjectData($data);
        $data['project']['remember_last_issue_field'] = json_decode($data['project']['remember_last_issue_field'], true);


        $projectFlagModel = new ProjectFlagModel();
        $isTableDisplayAvatar = $projectFlagModel->getValueByFlag($data['project_id'], "is_table_display_avatar");
        if(is_null($isTableDisplayAvatar)){
            $data['project']['is_table_display_avatar'] = "1";
        }else{
            $data['project']['is_table_display_avatar'] = $isTableDisplayAvatar;
        }
        //print_r($data['project']);die;
        $this->render('gitlab/project/setting_issue.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsIssueType()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $projectLogic = new ProjectLogic();
        $list = $projectLogic->typeList($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $data = [];
        $data['title'] = '事项类型';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'issue_type';

        $data['list'] = $list;

        $data = RewriteUrl::setProjectData($data);

        // 空数据
        $data['empty_data_msg'] = '无事项类型';
        $data['empty_data_status'] = 'list';  // bag|list|board|error|gps|id|off-line|search
        $data['empty_data_show_button'] = false;

        $this->render('gitlab/project/setting_issue_type.php', $data);
    }

    public function pageSettingsSprint()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $data = [];
        $data['title'] = '迭代管理';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'sprint';

        $data['query_str'] = http_build_query($_GET);

        $data = RewriteUrl::setProjectData($data);

        $this->render('gitlab/project/setting_sprint.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsVersion()
    {
        // $projectVersionModel = new ProjectVersionModel();
        // $list = $projectVersionModel->getByProject($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }
        $data = [];
        $data['title'] = '版本';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'version';

        $data['query_str'] = http_build_query($_GET);

        $data = RewriteUrl::setProjectData($data);

        $this->render('gitlab/project/setting_version.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsModule()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();

        $projectModuleModel = new ProjectModuleModel();
        //$list = $projectModuleModel->getByProjectWithUser($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);
        $count = $projectModuleModel->getAllCount($_GET[ProjectLogic::PROJECT_GET_PARAM_ID]);

        $data = [];
        $data['title'] = '模块';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'module';
        $data['users'] = $users;
        $data['query_str'] = http_build_query($_GET);
        //$data['list'] = $list;
        $data['count'] = $count;

        $data = RewriteUrl::setProjectData($data);
        $this->render('gitlab/project/setting_module.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsLabel()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $data = [];
        $data['title'] = '标签';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'label';
        $data['query_str'] = http_build_query($_GET);

        $data = RewriteUrl::setProjectData($data);
        $this->render('gitlab/project/setting_label.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsCatalog()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $data = [];
        $data['title'] = '分 类';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'catalog';
        $data['query_str'] = http_build_query($_GET);

        $data = RewriteUrl::setProjectData($data);
        $data['project_labels'] = ProjectLabelModel::getInstance()->getByProject($data['project_id']);
        $this->render('twig/project/setting_catalog.twig', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsLabelNew()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }
        $data = [];
        $data['title'] = '标签';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'label';
        $data['query_str'] = http_build_query($_GET);
        $data = RewriteUrl::setProjectData($data);
        $this->render('gitlab/project/setting_label_new.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsLabelEdit()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $id = isset($_GET['id']) && !empty($_GET['id']) ? intval($_GET['id']) : 0;
        if ($id > 0) {
            $projectLabelModel = new ProjectLabelModel();
            $info = $projectLabelModel->getById($id);

            $data = [];
            $data['title'] = '标签';
            $data['nav_links_active'] = 'setting';
            $data['sub_nav_active'] = 'label';

            $data['query_str'] = http_build_query($_GET);
            $data = RewriteUrl::setProjectData($data);

            $data['row'] = $info;
            $this->render('gitlab/project/setting_label_edit.php', $data);
        } else {
            echo 404;
            exit;
        }
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsPermission()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $data = [];
        $data['title'] = '权限';
        $data['nav_links_active'] = 'setting';
        $data['sub_nav_active'] = 'permission';
        $data = RewriteUrl::setProjectData($data);
        $this->render('gitlab/project/setting_permission.php', $data);
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsProjectMember()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }
        $memberCtrl = new Member();
        $memberCtrl->pageIndex();
    }

    /**
     * @throws \Exception
     */
    public function pageSettingsProjectRole()
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            if (!isset($this->projectPermArr[PermissionLogic::ADMINISTER_PROJECTS])) {
                $this->warn('提 示', '您没有权限访问该页面,需要项目管理权限');
                die;
            }
        }

        $roleCtrl = new Role();
        $roleCtrl->pageIndex();
    }

    /**
     * @throws \Exception
     */
    public function pageActivity()
    {
        $data = [];
        $data['title'] = 'Activity';
        $data['top_menu_active'] = 'time_line';
        $data['nav_links_active'] = 'home';
        $data['scrolling_tabs'] = 'activity';

        $this->render('gitlab/project/activity.php', $data);
    }

    /**
     * 项目统计页面
     * @throws \Exception
     */
    public function pageGantt()
    {
        $ctrl = new  Gantt();
        $ctrl->pageIndex();
    }


    /**
     * 项目统计页面
     * @throws \Exception
     */
    public function pageStat()
    {
        $statCtrl = new  Stat();
        $statCtrl->pageIndex();
    }


    /**
     * 迭代统计页面
     * @throws \Exception
     */
    public function pageStatSprint()
    {
        $statCtrl = new  StatSprint();
        $statCtrl->pageIndex();
    }

    /**
     * 获取项目信息
     * @param $id
     * @throws \Exception
     */
    public function fetch($id)
    {
        $id = intval($id);
        // 权限判断
        if (!empty($id)) {
            if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)
                && !PermissionLogic::checkUserHaveProjectItem(UserAuth::getId(), $id)) {
                $this->ajaxFailed('提 示', '您没有权限访问该项目,请联系管理员申请加入该项目');
            }
        }

        $projectLogic = new ProjectLogic();
        $project = $projectLogic->info($id);

        $this->ajaxSuccess('ok', $project);
    }


    /**
     * 新增项目
     * @param array $params
     * @throws \Exception
     */
    public function create($params = array())
    {
        if (!PermissionGlobal::check(UserAuth::getId(), PermissionGlobal::MANAGER_PROJECT_PERM_ID)) {
            $this->ajaxFailed('您没有权限进行此操作,系统管理才能创建项目');
        }
        if (empty($params)) {
            $this->ajaxFailed('错误', '无表单数据提交');
        }
        $err = [];
        $uid = $this->getCurrentUid();
        $projectModel = new ProjectModel($uid);
        $settingLogic = new SettingsLogic;
        $maxLengthProjectName = $settingLogic->maxLengthProjectName();
        $maxLengthProjectKey = $settingLogic->maxLengthProjectKey();

        if (!isset($params['name'])) {
            $err['project_name'] = '名称不存在';
        }
        if (isset($params['name']) && empty(trimStr($params['name']))) {
            $err['project_name'] = '名称不能为空';
        }
        if (isset($params['name']) && strlen($params['name']) > $maxLengthProjectName) {
            $err['project_name'] = '名称长度太长,长度应该小于' . $maxLengthProjectName;
        }
        if (isset($params['name']) && $projectModel->checkNameExist($params['name'])) {
            $err['project_name'] = '项目名称已经被使用了,请更换一个吧';
        }

        if (!isset($params['org_id'])) {
            //$err['org_id'] = '请选择一个组织';
            $params['org_id'] = 1; // 临时使用id为1的默认组织
        } elseif (isset($params['org_id']) && empty(trimStr($params['org_id']))) {
            $err['org_id'] = '组织不能为空';
        }
        $params['key'] = getFirstCharCode($params['name']);
       // echo $params['key'];
        if (!isset($params['key'])) {
            $err['project_key'] = '请输入KEY值';
           //$params['key'] = getFirstCharCode($params['name']);
        }
        if (isset($params['key']) && empty(trimStr($params['key']))) {
            $err['project_key'] = '关键字不能为空';
            //$params['key'] = getFirstCharCode($params['name']);
        }
        if (isset($params['key']) && strlen($params['key']) > $maxLengthProjectKey) {
            $err['project_key'] = '关键字长度太长,长度应该小于' . $maxLengthProjectKey;
        }
        if (isset($params['key']) && $projectModel->checkKeyExist($params['key'])) {
            $err['project_key'] = '项目关键字已经被使用了,请更换一个吧';
        }
        if (isset($params['key']) && !preg_match("/^[a-zA-Z0-9]+$/", $params['key'])) {
            $err['project_key'] = '项目关键字必须全部为英文字母,不能包含空格和特殊字符: ' . $params['key'];
        }

        $userModel = new UserModel();
        if (!isset($params['lead'])) {
            $err['project_lead'] = '请选择项目负责人.';
        } elseif (isset($params['lead']) && intval($params['lead']) <= 0) {
            $err['project_lead'] = '请选择项目负责人';
        } elseif (empty($userModel->getByUid($params['lead']))) {
            $err['project_lead'] = '项目负责人错误';
        }

        if (!isset($params['project_tpl_id'])) {
            $err['project_tpl_id'] = '请选择项目模板';
        } elseif (isset($params['project_tpl_id']) && empty(trimStr($params['project_tpl_id']))) {
            $err['project_tpl_id'] = '项目模板不能为空';
        }

        if (!empty($err)) {
            $this->ajaxFailed('创建项目失败,请检查表单.', $err, BaseCtrl::AJAX_FAILED_TYPE_FORM_ERROR);
        }

        //$params['key'] = mb_strtoupper(trimStr($params['key']));
        $params['key'] = trimStr($params['key']);
        $params['name'] = trimStr($params['name']);
        $params['project_tpl_id'] = intval($params['project_tpl_id']);

        if (!isset($params['lead']) || empty($params['lead'])) {
            $params['lead'] = $uid;
        }

        $info = [];
        $info['name'] = $params['name'];
        $info['org_id'] = $params['org_id'];
        $info['key'] = $params['key'];
        $info['lead'] = $params['lead'];
        $info['description'] = $params['description'];
        $info['project_tpl_id'] = $params['project_tpl_id'];
        $info['category'] = 0;
        $info['url'] = isset($params['url']) ? $params['url'] : '';
        $info['create_time'] = time();
        $info['create_uid'] = $uid;
        $info['avatar'] = !empty($params['avatar_relate_path']) ? $params['avatar_relate_path'] : '';
        $info['detail'] = isset($params['detail']) ? $params['detail'] : '';
        //$info['avatar'] = !empty($avatar) ? $avatar : "";
        try {
            $projectModel->db->beginTransaction();
            $orgModel = new OrgModel();
            $orgInfo = $orgModel->getById($params['org_id']);
            $info['org_path'] = $orgInfo['path'];
            $ret = ProjectLogic::create($info, $uid);

            if (!$ret['errorCode']) {
                //写入操作日志
                $logData = [];
                $logData['user_name'] = $this->auth->getUser()['username'];
                $logData['real_name'] = $this->auth->getUser()['display_name'];
                $logData['obj_id'] = 0;
                $logData['module'] = LogOperatingLogic::MODULE_NAME_PROJECT;
                $logData['page'] = $_SERVER['REQUEST_URI'];
                $logData['action'] = LogOperatingLogic::ACT_ADD;
                $logData['remark'] = '新建项目';
                $logData['pre_data'] = [];
                $logData['cur_data'] = $info;
                LogOperatingLogic::add($uid, 0, $logData);
                // 初始化甘特图设置
                $projectGantt = new ProjectGantt();
                $projectGantt->initGanttSetting($ret['data']['project_id']);
                $projectModel->db->commit();
                // 分发事件
                $info['id'] = $ret['data']['project_id'];
                $event = new CommonPlacedEvent($this, $info);
                $this->dispatcher->dispatch($event, Events::onProjectCreate);
            }
        } catch (\PDOException $e) {
            $projectModel->db->rollBack();
            $this->ajaxFailed('服务器错误', '添加失败,错误详情 :' . $ret['msg']);
        }
        $final = array(
            'project_id' => $ret['data']['project_id'],
            'key' => $params['key'],
            'org_name' => $orgInfo['name'],
            'path' => $orgInfo['path'] . '/' . $params['key'],
        );
        $this->ajaxSuccess('操作成功', $final);
    }
}
