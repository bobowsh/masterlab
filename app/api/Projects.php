<?php


namespace main\app\api;


use main\app\classes\JWTLogic;
use main\app\classes\LogOperatingLogic;
use main\app\classes\PermissionGlobal;
use main\app\classes\ProjectLogic;
use main\app\classes\SettingsLogic;
use main\app\event\CommonPlacedEvent;
use main\app\event\Events;
use main\app\model\issue\IssueModel;
use main\app\model\OrgModel;
use main\app\model\project\ProjectCatalogLabelModel;
use main\app\model\project\ProjectLabelModel;
use main\app\model\project\ProjectModel;
use main\app\model\project\ProjectModuleModel;
use main\app\model\project\ProjectUserRoleModel;
use main\app\model\project\ProjectVersionModel;
use main\app\model\SettingModel;
use main\app\model\user\UserModel;

/**
 * Class Projects
 * @package main\app\api
 */
class Projects extends BaseAuth
{

    public $isTriggerEvent = false;


    /**
     * @return array
     * @throws \Exception
     */
    public function v1()
    {
        if (in_array($this->requestMethod, self::$method_type)) {
            $handleFnc = $this->requestMethod . 'Handler';
            return $this->$handleFnc();
        }
        $this->isTriggerEvent = (bool)SettingModel::getInstance()->getSettingValue('api_trigger_event');
        return self::returnHandler('api方法错误');
    }

    /**
     * Restful GET , 获取项目列表 | 单个项目信息
     * {{API_URL}}/api/projects/v1/?access_token==xyz
     * {{API_URL}}/api/projects/v1/36?access_token==xyz
     * @return array
     * @throws \Exception
     */
    private function getHandler()
    {
        $projectId = 0;
        if (isset($_GET['_target'][3])) {
            $projectId = intval($_GET['_target'][3]);
        }
        if ($projectId > 0) {
            $projectLogic = new ProjectLogic();
            $final = $projectLogic->info($projectId);
        } else {
            $projectModel = new ProjectModel();
            $projects = $projectModel->filterByNameOrKey('');

            foreach ($projects as $key => &$item) {
                $item = ProjectLogic::formatProject($item);
            }
            unset($item);

            $final = array_values($projects);
        }

        return self::returnHandler('OK', $final);
    }

    /**
     * Restful PATCH ,更新项目名称和描述信息
     * {{API_URL}}/api/projects/v1/36?access_token==xyz
     * @return array
     * @throws \Exception
     */
    private function patchHandler()
    {
        $projectId = 0;
        if (isset($_GET['_target'][3])) {
            $projectId = intval($_GET['_target'][3]);
        }
        if ($projectId == 0) {
            return self::returnHandler('需要有项目ID', [], Constants::HTTP_BAD_REQUEST);
        }

        $reqDataArr = self::_PATCH();
        $fields = ['name', 'description'];
        $projectModel = new ProjectModel();
        $preData = $projectModel->getById($projectId);
        $row = [];
        foreach ($reqDataArr as $attrName => $attrVal) {
            if (in_array($attrName, $fields)) {
                $row[$attrName] = $attrVal;
            }
        }

        if (empty($row)) {
            return self::returnHandler('更新项目失败.', [], Constants::HTTP_BAD_REQUEST);
        }

        $ret = $projectModel->update($row, array('id' => $projectId));
        if($this->isTriggerEvent) {
            $afterData = $projectModel->getById($projectId);
            $event = new CommonPlacedEvent($this, ['pre_data' => $preData, 'cur_data' => $afterData]);
            $this->dispatcher->dispatch($event, Events::onProjectUpdate);
        }
        if ($ret[0]) {
            return self::returnHandler('更新项目成功');
        }

        return self::returnHandler('更新项目失败', [], Constants::HTTP_BAD_REQUEST);
    }

    /**
     * Restful DELETE ,删除某个项目
     * {{API_URL}}/api/projects/v1/36?access_token==xyz
     * @return array
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     * @throws \Exception
     */
    private function deleteHandler()
    {
        $uid = $this->masterUid;
        $projectId = 0;
        if (isset($_GET['_target'][3])) {
            $projectId = intval($_GET['_target'][3]);
        }
        if ($projectId == 0) {
            return self::returnHandler('需要有项目ID', [], Constants::HTTP_BAD_REQUEST);
        }

        $model = new ProjectModel($uid);

        $model->db->beginTransaction();

        $retDelProject = $model->deleteById($projectId);
        if ($retDelProject) {
            // 删除对应的事项
            $issueModel = new IssueModel();
            $issueModel->deleteItemsByProjectId($projectId);

            // 删除版本
            $projectVersionModel = new ProjectVersionModel($uid);
            $projectVersionModel->deleteByProject($projectId);

            // 删除模块
            $projectModuleModel = new ProjectModuleModel($uid);
            $projectModuleModel->deleteByProject($projectId);

            // 删除标签
            $projectLabelModel = new ProjectLabelModel();
            $projectLabelModel->deleteByProject($projectId);

            // 删除分类
            $projectCatalogLabelModel = new ProjectCatalogLabelModel();
            $projectCatalogLabelModel->deleteByProject($projectId);

            // 删除初始化的角色
            $projectUserRoleModel = new ProjectUserRoleModel();
            $projectUserRoleModel->deleteByProject($projectId);

        }
        $model->db->commit();

        return self::returnHandler('操作成功');
    }


    /**
     * Restful POST 创建项目
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    private function postHandler()
    {
        $err = [];
        $uid = $this->masterUid;
        $projectModel = new ProjectModel($uid);
        $settingLogic = new SettingsLogic;
        $maxLengthProjectName = $settingLogic->maxLengthProjectName();
        $maxLengthProjectKey = $settingLogic->maxLengthProjectKey();

        $params = [];
        $params = $_POST;

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
        //echo $params['key'];
        if (!isset($params['key'])) {
            $err['project_key'] = '请输入KEY值';
        }
        if (isset($params['key']) && empty(trimStr($params['key']))) {
            $err['project_key'] = '关键字不能为空';
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
            $err['project_lead'] = '需要有项目负责人.';
        } elseif (isset($params['lead']) && intval($params['lead']) <= 0) {
            $err['project_lead'] = '需要有项目负责人';
        } elseif (empty($userModel->getByUid($params['lead']))) {
            $err['project_lead'] = '项目负责人错误';
        }

        if (!isset($params['project_tpl_id'])) {
            $err['project_tpl_id'] = '请选择项目模板';
        } elseif (empty(trimStr($params['project_tpl_id']))) {
            $err['project_tpl_id'] = '项目模板不能为空';
        }

        if (!empty($err)) {
            return self::returnHandler('创建项目失败', $err, Constants::HTTP_BAD_REQUEST);
        }

        $params['key'] = trimStr($params['key']);
        $params['name'] = trimStr($params['name']);
        $params['type'] = 1;

        if (!isset($params['lead']) || empty($params['lead'])) {
            $params['lead'] = $uid;
        }

        $info = [];
        $info['name'] = $params['name'];
        $info['org_id'] = $params['org_id'];
        $info['key'] = $params['key'];
        $info['lead'] = $params['lead'];
        $info['description'] = isset($params['description']) ? $params['description'] : '';
        $info['project_tpl_id'] = $params['project_tpl_id'];
        $info['category'] = 0;
        $info['url'] = isset($params['url']) ? $params['url'] : '';
        $info['create_time'] = time();
        $info['create_uid'] = $uid;
        $info['avatar'] = !empty($params['avatar_relate_path']) ? $params['avatar_relate_path'] : '';
        $info['detail'] = isset($params['detail']) ? $params['detail'] : '';
        //$info['avatar'] = !empty($avatar) ? $avatar : "";

        $projectModel->db->beginTransaction();

        $orgModel = new OrgModel();
        $orgInfo = $orgModel->getById($params['org_id']);

        $info['org_path'] = $orgInfo['path'];

        $ret = ProjectLogic::create($info, $uid);
        if (!$ret['errorCode']) {
            $projectModel->db->commit();
            $final = array(
                'project_id' => $ret['data']['project_id'],
                'key' => $params['key'],
                'org_name' => $orgInfo['name'],
                'path' => $orgInfo['path'] . '/' . $params['key'],
            );
            $info['id'] = $ret['data']['project_id'];
            if($this->isTriggerEvent) {
                $event = new CommonPlacedEvent($this, $info);
                $this->dispatcher->dispatch($event, Events::onProjectCreate);
            }

            return self::returnHandler('操作成功', $final);
        } else {
            $projectModel->db->rollBack();
            return self::returnHandler('添加失败,错误详情 :' . $ret['msg'], [], Constants::HTTP_BAD_REQUEST);
        }
    }
}
