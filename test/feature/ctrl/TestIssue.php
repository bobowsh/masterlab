<?php

namespace main\test\unit\classes;

use main\app\classes\ProjectLogic;
use main\app\model\issue\IssueFilterModel;
use main\app\model\project\ProjectLabelModel;
use main\app\model\issue\IssueModel;
use main\app\model\issue\IssuePriorityModel;
use main\app\model\issue\IssueResolveModel;
use main\app\model\issue\IssueStatusModel;
use main\app\model\issue\IssueTypeModel;
use main\app\model\issue\IssueFileAttachmentModel;
use main\app\model\issue\IssueFollowModel;
use main\app\model\project\ProjectUserRoleModel;
use main\app\model\TimelineModel;
use main\app\model\user\UserModel;
use main\test\BaseAppTestCase;
use main\test\BaseDataProvider;

/**
 * 事项控制器测试类
 * Class TestIssueFilterLogic
 * @package main\test\logic
 */
class TestIssue extends BaseAppTestCase
{

    public static $org = [];

    public static $project = [];

    public static $issueArr = [];

    public static $sprint = [];

    public static $module = [];

    public static $version = [];

    public static $labelArr = [];

    public static $attachmentArr = [];

    public static $attachmentJsontArr = [];

    public static $projectUrl = '';

    public static $issue = [];

    public static $issueMaster = [];

    public static $issueChildrenArr = [];

    public static $userArr = [];

    public static $insertTimeLineIdArr = [];

    public static $issueIdArr = [];


    /**
     * @throws \Exception
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        self::$org = BaseDataProvider::createOrg();
        $info = [];
        $info['org_id'] = self::$org['id'];
        $info['org_path'] = self::$org['path'];
        self::$project = BaseDataProvider::createProject($info);
        self::$projectUrl = ROOT_URL . self::$org['path'] . '/' . self::$project['key'];

        $projectId = self::$project['id'];
        $userId = parent::$user['uid'];

        // @todo 赋予角色权限
        list($flagInitRole, $roleInfoArr) = ProjectLogic::initRole($projectId);
        $projectUserRoleModel = new ProjectUserRoleModel();
        if($flagInitRole){
            foreach ($roleInfoArr as $item) {
                $projectUserRoleModel->insertRole($userId,$projectId, $item['id']);
            }
        }

        $info = [];
        $info['project_id'] = self::$project['id'];
        $info['active'] = '1';
        self::$sprint = BaseDataProvider::createSprint($info);

        $info = [];
        $info['project_id'] = self::$project['id'];
        self::$version = BaseDataProvider::createProjectVersion(['project_id' => self::$project['id']]);

        $info = [];
        $info['project_id'] = self::$project['id'];
        self::$module = BaseDataProvider::createProjectModule($info);

        self::$labelArr[] = BaseDataProvider::createProjectModule();

        for ($i = 0; $i < 2; $i++) {
            list($uploadJson, self::$attachmentArr[]) = BaseDataProvider::createFineUploaderJson();
            $uploadJsonArr = json_decode($uploadJson, true);
            if (!empty($uploadJsonArr) && is_array($uploadJsonArr)) {
                self::$attachmentJsontArr[] = $uploadJsonArr[0];
            }
        }
        $timeLineModel = new TimelineModel();
        foreach (self::$insertTimeLineIdArr as $timeLineId) {
            $timeLineModel->deleteById($timeLineId);
        }
        // print_r(self::$attachmenJsontArr);

        for ($i = 0; $i < 20; $i++) {
            $info = [];
            $info['summary'] = 'UnitTest测试事项 ' . $i;
            $info['project_id'] = $projectId;
            $info['issue_type'] = IssueTypeModel::getInstance()->getIdByKey('bug');
            $info['priority'] = IssuePriorityModel::getInstance()->getIdByKey('high');
            $info['status'] = IssueStatusModel::getInstance()->getIdByKey('open');
            $info['resolve'] = IssueResolveModel::getInstance()->getIdByKey('done');
            $info['module'] = self::$module['id'];
            $info['sprint'] = self::$sprint['id'];
            $info['creator'] = $userId;
            $info['modifier'] = $userId;
            $info['reporter'] = $userId;
            $info['assignee'] = $userId;
            $info['updated'] = time();
            $info['start_date'] = date('Y-m-d');
            $info['due_date'] = date('Y-m-d', time() + 3600 * 24 * 7);
            $info['resolve_date'] = date('Y-m-d', time() + 3600 * 24 * 7);
            self::$issueArr[] = BaseDataProvider::createIssue($info);
        }
        self::$issue = BaseDataProvider::createIssue(['project_id'=>$projectId]);
        self::$issueMaster = BaseDataProvider::createIssue(['project_id'=>$projectId]);
    }

    /**
     * @throws \Exception
     */
    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        BaseDataProvider::deleteOrg(self::$org['id']);
        BaseDataProvider::deleteProject(self::$project['id']);
        BaseDataProvider::deleteSprint(self::$sprint['id']);
        BaseDataProvider::deleteProjectVersion(self::$version['id']);
        BaseDataProvider::deleteModule(self::$module['id']);

        $issueModel = new ProjectLabelModel();
        foreach (self::$labelArr as $item) {
            $issueModel->deleteById($item['id']);
        }

        $issueModel = new IssueFileAttachmentModel();
        foreach (self::$attachmentArr as $item) {
            $issueModel->deleteById($item['id']);
        }
        $issueModel = new UserModel();
        foreach (self::$userArr as $item) {
            $issueModel->deleteById($item['uid']);
        }

        $issueModel = new IssueModel();
        foreach (self::$issueIdArr as $issueId) {
            $issueModel->deleteById($issueId);
        }
        foreach (self::$issueArr as $item) {
            $issueModel->deleteById($item['id']);
        }
        if (!empty(self::$issue)) {
            $issueModel->deleteById(self::$issue['id']);
        }
        if (!empty(self::$issueMaster)) {
            $issueModel->deleteById(self::$issueMaster['id']);
        }
        foreach (self::$issueChildrenArr as $item) {
            $issueModel->deleteById($item['id']);
        }

        $table = $issueModel->getTable();
        $issues = $issueModel->db->fetchAll("select * from  {$table} where  summary LIKE '%测试事项%'");
        foreach ($issues as $issue) {
            $issueModel->deleteItemById($issue['id']);
        }
        $issues = $issueModel->db->fetchAll("select * from  {$table} where summary LIKE  '%test%'");
        foreach ($issues as $issue) {
            $issueModel->deleteItemById($issue['id']);
        }

        $issueFilterModel = new IssueFilterModel();
        $table = $issueFilterModel->getTable();
        $filters = $issueFilterModel->db->fetchAll("select * from  {$table} where name LIKE '%testSaveFilterName%'");
        foreach ($filters as $filter) {
            $issueFilterModel->deleteItemById($filter['id']);
        }
    }

    /**
     * 测试页面
     * @throws \Exception
     */
    public function testIndexPage()
    {
        $curl = BaseAppTestCase::$userCurl;
        parent::curlGet($curl, self::$projectUrl . '/issues');
        $resp = $curl->rawResponse;
        parent::checkPageError($curl);
        $this->assertRegExp('/<title>.+<\/title>/', $resp, 'expect <title> tag, but not match');
        preg_match_all('/var\s+_issueConfig\s*=\s*\{(.+)\}\;/sU', $resp, $result, PREG_PATTERN_ORDER);
        $issueConfig = "{" . $result[1][0] . "}";
        $issueConfigArr = json_decode($issueConfig, true);

        $this->assertTrue(isset($issueConfigArr['priority']));
        $this->assertTrue(isset($issueConfigArr['issue_types']));
        $this->assertTrue(isset($issueConfigArr['issue_status']));
        $this->assertTrue(isset($issueConfigArr['issue_resolve']));
        $this->assertTrue(isset($issueConfigArr['issue_module']));
        $this->assertTrue(isset($issueConfigArr['issue_version']));
        $this->assertTrue(isset($issueConfigArr['issue_labels']));
        $this->assertTrue(isset($issueConfigArr['users']));
        $this->assertTrue(isset($issueConfigArr['projects']));
    }

    /**
     * @throws \Exception
     */
    public function testDetailPage()
    {
        $curl = BaseAppTestCase::$userCurl;
        $issue = self::$issueArr[0];
        parent::curlGet($curl, ROOT_URL . '/issue/detail/index/' . $issue['id']);
        $resp = $curl->rawResponse;
        parent::checkPageError($curl);
        $this->assertRegExp('/<title>.+<\/title>/', $resp, 'expect <title> tag, but not match');
        preg_match_all('/var\s+_issueConfig\s*=\s*\{(.+)\}\;/sU', $resp, $result, PREG_PATTERN_ORDER);
        $issueConfig = "{" . $result[1][0] . "}";
        $issueConfigArr = json_decode($issueConfig, true);
        $this->assertTrue(isset($issueConfigArr['priority']));
        $this->assertTrue(isset($issueConfigArr['issue_types']));
        $this->assertTrue(isset($issueConfigArr['issue_status']));
        $this->assertTrue(isset($issueConfigArr['issue_resolve']));
        $this->assertTrue(isset($issueConfigArr['issue_module']));
        $this->assertTrue(isset($issueConfigArr['issue_version']));
        $this->assertTrue(isset($issueConfigArr['issue_labels']));
        $this->assertTrue(isset($issueConfigArr['users']));
        $this->assertTrue(isset($issueConfigArr['projects']));
    }

    /**
     * @throws \Exception
     */
    public function testFilter()
    {
        $projectId = self::$project['id'];
        $user = parent::$user;

        // 无参数查询
        $curl = BaseAppTestCase::$userCurl;
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter?project=' . $projectId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if (!$respArr) {
            $this->fail(__FUNCTION__ . ' failed');
            return;
        }

        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr)+2, intval($respArr['data']['total']));

        // 用户名查询
        $param = [];
        $param['project'] = $projectId;
        $param['assignee_username'] = $user['username'];
        $param['assignee'] = $user['uid'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 用户id查询
        $param = [];
        $param['project'] = $projectId;
        $param['author'] = $user['uid'];
        $param['reporter_uid'] = $user['uid'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);

        // 事项标题查询
        $param = [];
        $param['project'] = $projectId;
        $param['search'] = 'UnitTest测试事项';//self::$issueArr[0]['summary'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 按模块查询
        $param = [];
        $param['project'] = $projectId;
        $param['module'] = self::$module['name'];
        $param['module_id'] = self::$module['id'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 按优先级查询
        $param = [];
        $param['project'] = $projectId;
        $param['priority'] = 'high';
        $param['priority_id'] = IssuePriorityModel::getInstance()->getIdByKey('high');
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 按解决结果查询
        $param = [];
        $param['project'] = $projectId;
        $param['resolve'] = 'done';
        $param['resolve_id'] = IssueResolveModel::getInstance()->getIdByKey('done');
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 按状态查询
        $param = [];
        $param['project'] = $projectId;
        $param['status'] = 'open';
        $param['status_id'] = IssueStatusModel::getInstance()->getIdByKey('open');
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr)+2, intval($respArr['data']['total']));

        // 按创建时间的范围
        $param = [];
        $param['project'] = $projectId;
        $param['created_start'] = time() - 100;
        $param['created_end'] = time() + 10;
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr)+2, intval($respArr['data']['total']));

        // 按更新时间的范围
        $param = [];
        $param['project'] = $projectId;
        $param['updated_start'] = time() - 100;
        $param['updated_end'] = time();
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 排序
        $param = [];
        $param['project'] = $projectId;
        $param['sort_field'] = 'id';
        $param['sort_by'] = 'DESC';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertTrue($respArr['data']['issues'][0]['id'] > $respArr['data']['issues'][1]['id']);

        // 过滤器 分配给我的
        $param = [];
        $param['project'] = $projectId;
        $param['sys_filter'] = 'assignee_mine';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 过滤器 我报告的
        $param = [];
        $param['project'] = $projectId;
        $param['sys_filter'] = 'my_report';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));

        // 过滤器 最近解决的
        $param = [];
        $param['project'] = $projectId;
        $param['sys_filter'] = 'recently_resolve';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertEmpty($respArr['data']['issues']);
        $this->assertEmpty($respArr['data']['pages']);

        // 过滤器 最近更新的
        $param = [];
        $param['project'] = $projectId;
        $param['sys_filter'] = 'update_recently';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr)+2, intval($respArr['data']['total']));

        // 过滤器 打开的
        $param = [];
        $param['project'] = $projectId;
        $param['sys_filter'] = 'open';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr)+2, intval($respArr['data']['total']));

        // 所有条件都满足的查询
        $param = [];
        $param['project'] = $projectId;
        $param['assignee_username'] = $user['username'];
        $param['assignee'] = $user['uid'];
        $param['author'] = $user['uid'];
        $param['reporter_uid'] = $user['uid'];
        $param['search'] = substr(self::$issueArr[0]['summary'], 0, -2);
        $param['module'] = self::$module['name'];
        $param['module_id'] = self::$module['id'];
        $param['priority'] = 'high';
        $param['priority_id'] = IssuePriorityModel::getInstance()->getIdByKey('high');
        $param['resolve'] = 'done';
        $param['resolve_id'] = IssueResolveModel::getInstance()->getIdByKey('done');
        $param['status'] = 'open';
        $param['status_id'] = IssueStatusModel::getInstance()->getIdByKey('open');
        $param['created_start'] = time() - 100;
        $param['created_end'] = time() + 100;
        $param['updated_start'] = time() - 100;
        $param['updated_end'] = time() + 100;
        $param['sort_field'] = 'id';
        $param['sort_by'] = 'DESC';
        parent::curlGet($curl, ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));
    }

    /**
     * 测试自定义过滤器
     * @throws \Exception
     */
    public function testSaveFilter()
    {
        $projectId = self::$project['id'];
        $user = parent::$user;
        $curl = BaseAppTestCase::$userCurl;

        // 保存一个过滤器
        $filterArr = [];
        $filterArr['project'] = $projectId;
        $filterArr['assignee'] = $user['uid'];
        $filterArr['author_username'] = $user['username'];
        $filterArr['status'] = 'open';
        $filterArr['priority'] = 'high';
        $filterArr['resolve_resolve'] = 'done';

        $param = [];
        $param['name'] = $filterName = 'testSaveFilterName_' . mt_rand(10000, 999999);
        $param['filter'] = http_build_query($filterArr);
        $param['description'] = 'test';
        parent::curlGet($curl, ROOT_URL . 'issue/main/saveFilter', $param);
        //echo self::$userCurl->rawResponse;
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }

        // 查询过滤器
        parent::curlGet($curl, ROOT_URL . 'issue/main/getFavFilter');
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertNotEmpty($respArr['data']['filters']);
        list($firstFilters, $hideFilters) = $respArr['data']['filters'];
        $savedFilter = [];
        foreach ($firstFilters as $item) {
            if ($item['name'] == $filterName) {
                $savedFilter = $item;
                break;
            }
        }
        foreach ($hideFilters as $item) {
            if ($item['name'] == $filterName) {
                $savedFilter = $item;
                break;
            }
        }
        $this->assertNotEmpty($savedFilter);

        // 使用过滤器查询
        $param = [];
        $param['fav_filter'] = $savedFilter['id'];
        $curl->get(ROOT_URL . 'issue/main/filter', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertEquals('200', $respArr['ret']);
        $this->assertNotEmpty($respArr['data']['issues']);
        $this->assertNotEmpty($respArr['data']['pages']);
        $this->assertEquals(count(self::$issueArr), intval($respArr['data']['total']));
    }

    /**
     * @throws \Exception
     */
    public function testFetchIssueTypeAndUi()
    {
        $projectId = self::$project['id'];
        $curl = BaseAppTestCase::$userCurl;
        // 获取事项类型
        $param = [];
        $param['project_id'] = $projectId;
        parent::curlGet($curl, ROOT_URL . 'issue/main/fetchIssueType');
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertNotEmpty($respArr['data']['issue_types']);
        $issueTypesArr = $respArr['data']['issue_types'];
        $issueTypeId = current($issueTypesArr)['id'];
        $uiTypeArr = ['create', 'edit'];
        foreach ($uiTypeArr as $uiType) {
            $param = [];
            $param['project_id'] = $projectId;
            $param['issue_type_id'] = $issueTypeId;
            $param['type'] = $uiType;
            parent::curlGet($curl, ROOT_URL . 'issue/main/fetchUiConfig', $param);
            parent::checkPageError($curl);
            $respArr = json_decode(self::$userCurl->rawResponse, true);
            if ($respArr['ret'] != '200') {
                $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            }
            $this->assertNotEmpty($respArr['data']['configs']);
            $this->assertNotEmpty($respArr['data']['fields']);
            $this->assertNotEmpty($respArr['data']['field_types']);
            $this->assertNotEmpty($respArr['data']['allow_add_status']);
        }
    }

    /**
     * @throws \Exception
     */
    public function testAddFetchUpdate()
    {
        // 获得标签id
        $labelsArr = [];
        foreach (self::$labelArr as $label) {
            $labelsArr[] = $label['id'];
        }
        $versionArr = [];
        $versionArr[] = self::$version['id'];

        $projectId = self::$project['id'];
        $userId = parent::$user['uid'];
        $info = [];
        $info['summary'] = '测试事项';
        $info['project_id'] = $projectId;
        $info['issue_type'] = IssueTypeModel::getInstance()->getIdByKey('bug');
        $info['priority'] = IssuePriorityModel::getInstance()->getIdByKey('high');
        $info['status'] = IssueStatusModel::getInstance()->getIdByKey('open');
        $info['resolve'] = IssueResolveModel::getInstance()->getIdByKey('done');
        $info['module'] = self::$module['id'];
        $info['sprint'] = self::$sprint['id'];
        $info['creator'] = $userId;
        $info['reporter'] = $userId;
        $info['assignee'] = $userId;
        $info['start_date'] = date('Y-m-d');
        $info['due_date'] = date('Y-m-d', time() + 3600 * 24 * 7);
        $info['resolve_date'] = date('Y-m-d', time() + 3600 * 24 * 7);
        $info['labels'] = $labelsArr;
        $info['attachment'] = json_encode(self::$attachmentJsontArr);
        $info['fix_version'] = $versionArr;
        $param = [];
        $param['params'] = $info;
        $curl = BaseAppTestCase::$userCurl;
        parent::curlPost($curl, ROOT_URL . 'issue/main/add', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $issueId = $respArr['data'];
        self::$issueIdArr[] = $issueId;

        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlGet($curl, ROOT_URL . 'issue/main/fetchIssueEdit', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertNotEmpty($respArr['data']['configs']);
        $this->assertNotEmpty($respArr['data']['fields']);
        $this->assertNotEmpty($respArr['data']['field_types']);
        $this->assertNotEmpty($respArr['data']['project_module']);
        $this->assertNotEmpty($respArr['data']['issue']);

        $fetchIssue = $respArr['data']['issue'];
        foreach ($info as $field => $value) {
            if (isset($fetchIssue[$field]) && $field != 'attachment') {
                $this->assertEquals($value, $fetchIssue[$field], 'Field ' . $field . ' not equal');
            }
        }
        $originUuidArr = [];
        foreach (self::$attachmentJsontArr as $item) {
            $originUuidArr[] = $item['uuid'];
        }
        $addedUuidArr = [];
        foreach ($fetchIssue['attachment'] as $item) {
            $addedUuidArr[] = $item['uuid'];
        }
        $this->assertEquals($originUuidArr, $addedUuidArr);

        // detail 页面的get方法
        $param = [];
        $param['id'] = $issueId;
        parent::curlGet($curl, ROOT_URL . 'issue/detail/get', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $getIssue = $respArr['data']['issue'];
        // print_r($getIssue);
        $this->assertNotEmpty($getIssue['module_name']);
        $this->assertNotEmpty($getIssue['fix_version_names']);
        $this->assertNotEmpty($getIssue['issue_type_info']);
        $this->assertNotEmpty($getIssue['resolve_info']);
        $this->assertNotEmpty($getIssue['status_info']);
        $this->assertNotEmpty($getIssue['priority_info']);
        $this->assertNotEmpty($getIssue['labels_names']);
        $this->assertNotEmpty($getIssue['labels']);
        $this->assertNotEmpty($getIssue['attachment']);
        $this->assertNotEmpty($getIssue['assignee_info']);
        $this->assertNotEmpty($getIssue['reporter_info']);
        $this->assertNotEmpty($getIssue['creator_info']);
        $this->assertNotEmpty($getIssue['allow_update_status']);
        $this->assertNotEmpty($getIssue['allow_update_resolves']);

        // 更新操作
        $info = [];
        $info['summary'] = '测试事项';
        $info['priority'] = IssuePriorityModel::getInstance()->getIdByKey('import');
        $info['status'] = IssueStatusModel::getInstance()->getIdByKey('closed');
        $info['resolve'] = IssueResolveModel::getInstance()->getIdByKey('fixed');
        $info['module'] = 0;
        $info['sprint'] = 0;
        $info['modifier'] = $userId;
        $info['start_date'] = date('Y-m-d', time() - 3600 * 24 * 1);
        $info['due_date'] = date('Y-m-d', time() + 3600 * 24 * 6);
        $info['resolve_date'] = date('Y-m-d', time() + 3600 * 24 * 6);
        $info['labels'] = [];
        $info['attachment'] = json_encode([]);
        $param = [];
        $param['params'] = $info;
        $curl = BaseAppTestCase::$userCurl;
        parent::curlPost($curl, ROOT_URL . 'issue/main/update?issue_id=' . $issueId, $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }

        // 再次获取
        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlGet($curl, ROOT_URL . 'issue/main/fetchIssueEdit', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $this->assertNotEmpty($respArr['data']['configs']);
        $this->assertNotEmpty($respArr['data']['fields']);
        $this->assertNotEmpty($respArr['data']['field_types']);
        $this->assertNotEmpty($respArr['data']['project_module']);
        $this->assertNotEmpty($respArr['data']['issue']);

        $fetchIssue = $respArr['data']['issue'];
        foreach ($info as $field => $value) {
            if (isset($fetchIssue[$field]) && $field != 'attachment') {
                $this->assertEquals($value, $fetchIssue[$field], 'Field ' . $field . ' not equal');
            }
        }
        $this->assertEmpty($fetchIssue['attachment']);
    }

    /**
     * @throws \Exception
     */
    public function testPatch()
    {
        self::$userArr[] = $user = BaseDataProvider::createUser();
        self::$issueArr[] = $issue = BaseDataProvider::createIssue();
        $issueId = $issue['id'];
        $param = [];
        $param['issue']['assignee_id'] = $user['uid'];
        $curl = parent::$userCurl;
        $url = ROOT_URL . 'issue/main/patch?id=' . $issueId;
        parent::packUnitTestUrl($url);
        $curl->patch($url, $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        $this->assertNotEmpty($respArr);
        $this->assertEquals($respArr['assignee_id'], $user['uid']);
        $issueModel = new IssueModel();
        $issue = $issueModel->getById($issueId);
        $this->assertEquals($user['uid'], $issue['assignee']);
    }

    /**
     * @throws \Exception
     */
    public function testUploadAndDelete()
    {
        // 上传
        $curl = parent::$userCurl;
        parent::curlPost($curl, ROOT_URL . 'issue/main/upload/'.parent::$project['id'], array(
            'qqfile' => new \CURLFile(PUBLIC_PATH . 'attachment/unittest/sample.png'),
        ));
        parent::checkPageError($curl);
        $respArr = json_decode($curl->rawResponse, true);
        $this->assertNotEmpty($respArr);
        $this->assertEquals('', $respArr['error']);
        $this->assertNotEmpty($respArr['url']);
        $this->assertNotEmpty($respArr['insert_id']);
        $model = new IssueFileAttachmentModel();
        self::$attachmentArr[] = $attachment = $model->getById($respArr['insert_id']);
        // 删除
        $uuid = $respArr['uuid'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/uploadDelete/'.parent::$project['id'], ['uuid' => $uuid]);
        //echo $curl->rawResponse;
        parent::checkPageError($curl);
        $respArr = json_decode($curl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ',' . $respArr['data']);
            return;
        }
        $this->assertEmpty($model->getById($attachment['id']));
    }


    /**
     * @throws \Exception
     */
    public function testFollowAndUnFollow()
    {
        $issueId = self::$issue['id'];
        $curl = BaseAppTestCase::$userCurl;
        parent::curlGet($curl, ROOT_URL . 'issue/main/follow?issue_id=' . $issueId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $this->assertTrue($respArr['data'][0]);
        $insertId = $respArr['data'][1];
        $model = new IssueFollowModel();
        $row = $model->getById($insertId);
        $this->assertNotEmpty($row);
        // 检查关注的数据
        $followModel = new IssueFollowModel();
        $followRow = $followModel->getItemsByIssueUserId($issueId, parent::$user['uid']);
        $this->assertNotEmpty($followRow);


        parent::curlGet($curl, ROOT_URL . 'issue/main/unFollow?issue_id=' . $issueId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $row = $model->getById($insertId);
        $this->assertEmpty($row);
    }

    /**
     * @throws \Exception
     */
    public function testIssueChild()
    {
        $curl = BaseAppTestCase::$userCurl;
        $issueMaster = self::$issueMaster;
        $issueId = self::$issue['id'];
        $masterIssueId = $issueMaster['id'];

        // 转换为子任务
        $param = [];
        $param['issue_id'] = $issueId;
        $param['master_id'] = $masterIssueId;
        parent::curlPost($curl, ROOT_URL . 'issue/main/convertChild', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }
        $issueModel = new IssueModel();
        $issueChild = $issueModel->getById($issueId);
        $this->assertEquals($issueChild['master_id'], $masterIssueId);
        $issueMaster = $issueModel->getById($masterIssueId);
        $this->assertEquals($issueMaster['have_children'], '1');

        // 获取子任务列表
        parent::curlGet($curl, ROOT_URL . 'issue/main/getChildIssues?issue_id=' . $masterIssueId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertNotEmpty($respArr['data']['children']);

        // 移除子任务
        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlPost($curl, ROOT_URL . 'issue/main/removeChild', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
            return;
        }

        // 再次获取子任务列表
        parent::curlGet($curl, ROOT_URL . 'issue/main/getChildIssues?issue_id=' . $masterIssueId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertEmpty($respArr['data']['children']);

        // 设置多个子任务
        $childrenNum = 4;
        for ($i = 0; $i < $childrenNum; $i++) {
            $param = [];
            $param['issue_id'] = self::$issueArr[$i]['id'];
            $param['master_id'] = $masterIssueId;
            $curl->post(ROOT_URL . 'issue/main/convertChild', $param);
        }
        parent::curlGet($curl, ROOT_URL . 'issue/main/getChildIssues?issue_id=' . $masterIssueId);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertNotEmpty($respArr['data']['children']);
        $this->assertCount($childrenNum, $respArr['data']['children']);
    }

    /**
     * @throws \Exception
     */
    public function testEditorMdUploadAndDelete()
    {
        // 上传
        $curl = parent::$userCurl;
        parent::curlPost($curl, ROOT_URL . 'issue/detail/editormdUpload', array(
            'editormd-image-file' => new \CURLFile(PUBLIC_PATH . 'attachment/unittest/sample.png'),
        ));
        parent::checkPageError($curl);
        $respArr = json_decode($curl->rawResponse, true);
        $this->assertNotEmpty($respArr);
        $this->assertEquals(1, $respArr['success']);
        $this->assertNotEmpty($respArr['url']);
        $this->assertNotEmpty($respArr['uuid']);
        $this->assertNotEmpty($respArr['insert_id']);

        $model = new IssueFileAttachmentModel();
        self::$attachmentArr[] = $attachment = $model->getById($respArr['insert_id']);
        // 删除
        $uuid = $respArr['uuid'];
        parent::curlGet($curl, ROOT_URL . 'issue/main/uploadDelete/'.parent::$project['id'], ['uuid' => $uuid]);
        parent::checkPageError($curl);
        $respArr = json_decode($curl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ',' . $respArr['data']);
            return;
        }
        $this->assertEmpty($model->getById($attachment['id']));
    }

    /**
     * @throws \Exception
     */
    public function testTimeLine()
    {
        $issueId = self::$issue['id'];
        $curl = BaseAppTestCase::$userCurl;
        // 新增事项
        $param = [];
        $param['issue_id'] = $issueId;
        $param['content'] = 'test-content';
        $param['content_html'] = 'test-content-html';
        $param['reopen'] = '1';
        parent::curlPost($curl, ROOT_URL . 'issue/detail/addTimeline', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ':' . $respArr['data']);
            return;
        }
        $insertId = $respArr['data'];
        self::$insertTimeLineIdArr[] = $insertId;
        $model = new TimelineModel();
        $timeLine = $model->getRowById($insertId);
        $this->assertNotEmpty($timeLine);
        $this->assertEquals($issueId, $timeLine['issue_id']);
        $this->assertEquals($param['content'], $timeLine['content']);
        $this->assertEquals($param['content_html'], $timeLine['content_html']);

        $issueModel = new IssueModel();
        $issue = $issueModel->getById($issueId);
        $reopenStatusId = IssueStatusModel::getInstance()->getIdByKey('reopen');
        $this->assertEquals($reopenStatusId, $issue['status']);

        // 更新工作日志
        $param = [];
        $param['id'] = $insertId;
        $param['content'] = 'test-content-updated';
        $param['content_html'] = 'test-content-html-updated';
        $curl->post(ROOT_URL . 'issue/detail/updateTimeline', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ':' . $respArr['data']);
            return;
        }

        $model = new TimelineModel();
        $timeLine = $model->getRowById($insertId);
        $this->assertNotEmpty($timeLine);
        $this->assertEquals($issueId, $timeLine['issue_id']);
        $this->assertEquals($param['content'], $timeLine['content']);
        $this->assertEquals($param['content_html'], $timeLine['content_html']);

        // 某一事项的获取工作日志列表
        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlGet($curl, ROOT_URL . 'issue/detail/fetchTimeline', $param);
        parent::checkPageError($curl);
        $respArr = json_decode($curl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ':' . $respArr['data']);
        }
        $this->assertNotEmpty($respArr['data']['timelines']);

        // 删除工作日志
        $param = [];
        $param['id'] = $insertId;
        parent::curlGet($curl, ROOT_URL . 'issue/detail/deleteTimeline', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlGet($curl, ROOT_URL . 'issue/detail/fetchTimeline', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg']);
        }
        $this->assertEmpty($respArr['data']['timelines']);
    }

    /**
     * @throws \Exception
     */
    public function testDelete()
    {
        $issueId = self::$issue['id'];
        $curl = BaseAppTestCase::$userCurl;
        // 删除任务
        $param = [];
        $param['issue_id'] = $issueId;
        parent::curlPost($curl, ROOT_URL . 'issue/main/delete', $param);
        parent::checkPageError($curl);
        $respArr = json_decode(self::$userCurl->rawResponse, true);
        if ($respArr['ret'] != '200') {
            $this->fail(__FUNCTION__ . ' failed:' . $respArr['msg'] . ':' . $respArr['data']);
            return;
        }
        $issueModel = new IssueModel();
        $issue = $issueModel->getById($issueId);
        $this->assertEmpty($issue);
    }
}
