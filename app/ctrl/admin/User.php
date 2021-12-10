<?php

namespace main\app\ctrl\admin;

use main\app\classes\PermissionGlobal;
use main\app\classes\SystemLogic;
use main\app\classes\UploadLogic;
use main\app\classes\UserAuth;
use main\app\classes\ConfigLogic;
use main\app\classes\UserLogic;
use main\app\ctrl\BaseCtrl;
use main\app\ctrl\BaseAdminCtrl;
use main\app\event\Events;
use main\app\event\CommonPlacedEvent;
use main\app\model\user\UserGroupModel;
use main\app\model\user\UserModel;
use main\app\model\user\GroupModel;


/**
 * 系统模块的用户控制器
 */
class User extends BaseAdminCtrl
{
    public static $pageSizes = [10, 20, 50, 100];

    /**
     * User constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $userId = UserAuth::getId();
        $this->addGVar('top_menu_active', 'system');
        $check = PermissionGlobal::check($userId, PermissionGlobal::MANAGER_USER_PERM_ID);

        if (!$check) {
            $this->error('权限错误', '您还未获取此模块的权限！');
            exit;
        }
    }

    /**
     * @throws \Exception
     */
    public function pageIndex()
    {
        $data = [];
        $data['title'] = '用户管理';
        $data['nav_links_active'] = 'user';
        $data['left_nav_active'] = 'user';
        ConfigLogic::getAllConfigs($data);

        $data['group_id'] = 0;
        if (isset($_GET['group_id'])) {
            $data['group_id'] = (int)$_GET['group_id'];
        }
        $data['status_normal'] = UserModel::STATUS_NORMAL;
        $data['status_disabled'] = UserModel::STATUS_DISABLED;
        $data['status_approval'] = UserModel::STATUS_PENDING_APPROVAL;
        $data['default_avatar'] = '/gitlab/images/default_user.png';
        $this->render('twig/admin/user/users.twig', $data);
    }

    /**
     *
     * @return int|null
     */
    private function getParamUserId()
    {
        $userId = null;
        if (isset($_GET['_target'][3])) {
            $userId = (int)$_GET['_target'][3];
        }
        if (isset($_REQUEST['uid'])) {
            $userId = (int)$_REQUEST['uid'];
        }
        if (!$userId) {
            $this->ajaxFailed('uid_is_null');
        }
        return $userId;
    }

    /**
     * 用户查询
     * @param int $uid
     * @param string $username
     * @param int $group_id
     * @param string $status
     * @param string $order_by
     * @param string $sort
     * @param int $page
     * @param int $page_size
     * @throws \Exception
     */
    public function filter(
        $uid = 0,
        $username = '',
        $group_id = 0,
        $status = '',
        $order_by = 'uid',
        $sort = 'desc',
        $page = 1,
        $page_size = 20
    )
    {
        $groupId = intval($group_id);
        $orderBy = $order_by;
        $pageSize = intval($page_size);
        if (!in_array($pageSize, self::$pageSizes)) {
            $pageSize = self::$pageSizes[1];
        }
        $uid = intval($uid);
        $groupId = intval($groupId);
        $username = trimStr($username);
        $status = intval($status);

        $userLogic = new UserLogic();
        $ret = $userLogic->filter($uid, $username, $groupId, $status, $orderBy, $sort, $page, $pageSize);
        list($users, $total, $groups) = $ret;
        $data['groups'] = array_values($groups);
        $data['total'] = $total;
        $data['pages'] = ceil($total / $pageSize);
        $data['page_size'] = $pageSize;
        $data['page'] = $page;
        $data['users'] = array_values($users);
        $data['cur_group_id'] = $groupId;
        $this->ajaxSuccess('ok', $data);
    }


    /**
     * 禁用用户
     * @throws \Exception
     */
    public function disable()
    {
        $userId = $this->getParamUserId();
        $userInfo = [];
        $userModel = UserModel::getInstance();
        $userInfo['status'] = UserModel::STATUS_DISABLED;
        $userModel->uid = $userId;
        $userModel->updateUser($userInfo);
        // 分发事件
        $event = new CommonPlacedEvent($this, $user=$userModel->getByUid($userId));
        $this->dispatcher->dispatch($event,  Events::onUserDisableByAdmin);
        $this->ajaxSuccess('操作成功');
    }

    /**
     * 激活用户
     * @throws \Exception
     */
    public function active()
    {
        $userId = $this->getParamUserId();
        $userInfo = [];
        $userModel = UserModel::getInstance();
        $userInfo['status'] = UserModel::STATUS_NORMAL;
        $userModel->uid = $userId;
        $userModel->updateUser($userInfo);
        // 分发事件
        $event = new CommonPlacedEvent($this, $user = $userModel->getByUid($userId));
        $this->dispatcher->dispatch($event,  Events::onUserActiveByAdmin);
        $this->ajaxSuccess('提示','操作成功');
    }

    /**
     * 获取单个用户信息
     * @throws \Exception
     */
    public function get()
    {
        $userId = $this->getParamUserId();
        $userModel = UserModel::getInstance($userId);

        $userModel->uid = $userId;
        $user = $userModel->getUser();
        if (isset($user['password'])) {
            unset($user['password']);
        }
        if (!isset($user['uid'])) {
            $this->ajaxFailed('参数错误');
        }
        UserLogic::formatAvatarUser($user);

        $user['is_cur'] = "0";
        if ($user['uid'] == UserAuth::getId()) {
            $user['is_cur'] = "1";
        }
        $this->ajaxSuccess('ok', (object)$user);
    }

    /**
     * 用户
     * @throws \Exception
     */
    public function gets()
    {
        $userLogic = new UserLogic();
        $users = $userLogic->getAllNormalUser();
        $this->ajaxSuccess('ok', $users);
    }

    /**
     * @throws \Exception
     */
    public function userGroup()
    {
        $userId = $this->getParamUserId();
        $data = [];
        $userGroupModel = new UserGroupModel();
        $data['user_groups'] = $userGroupModel->getGroupsByUid($userId);
        $groupModel = new GroupModel();
        $data['groups'] = $groupModel->getAll(false);
        $this->ajaxSuccess('ok', $data);
    }

    /**
     *
     * @param $params
     * @throws \Exception
     */
    public function updateUserGroup($params)
    {
        $userId = $this->getParamUserId();
        $groups = $params['groups'];
        if (!is_array($groups)) {
            $this->ajaxFailed('param_is_error');
        }
        $userLogic = new UserLogic();
        list($ret, $msg) = $userLogic->updateUserGroup($userId, $groups);
        if ($ret) {
            $this->ajaxSuccess("提示", '操作成功');
        }
        $this->ajaxFailed($msg);
    }

    /**
     * 添加用户
     * @param $params
     * @throws
     */
    public function add($params)
    {
        $errorMsg = [];
        if (empty($params)) {
            $this->ajaxFailed('参数错误', '提交的数据为空');
        }
        if (!isset($params['password']) || empty($params['password'])) {
            $errorMsg['password'] = '请输入密码';
        }
        if (!isset($params['email']) || empty($params['email'])) {
            $errorMsg['email'] = '请输入email地址';
        }
        if (!isset($params['username']) || empty($params['username'])) {
            $errorMsg['username'] = '请输入用户名';
        }
        if (!isset($params['display_name']) || empty($params['display_name'])) {
            $errorMsg['display_name'] = '请输入显示名称';
        }
        if (!empty($errorMsg)) {
            $this->ajaxFailed('参数错误', $errorMsg, BaseCtrl::AJAX_FAILED_TYPE_FORM_ERROR);
        }

        $display_name = $params['display_name'];
        $password = trimStr($params['password']);
        $username = trimStr($params['username']);
        $email = trimStr($params['email']);
        $disabled = isset($params['disable']) ? true : false;
        $userInfo = [];
        $userInfo['email'] = str_replace(' ', '', $email);
        $userInfo['username'] = $username;
        $userInfo['display_name'] = $display_name;
        $userInfo['password'] = UserAuth::createPassword($password);
        $userInfo['is_verified'] = '0';
        $userInfo['create_time'] = time();
        $userInfo['title'] = isset($params['title']) ? $params['title'] : '';
        if ($disabled) {
            $userInfo['status'] = UserModel::STATUS_DISABLED;
        } else {
            $userInfo['status'] = UserModel::STATUS_NORMAL;
        }

        $userModel = UserModel::getInstance();
        $user = $userModel->getByEmail($email);
        if (isset($user['email'])) {
            $this->ajaxFailed('邮箱地址已经被使用了');
        }
        $user2 = $userModel->getByUsername($username);
        if (isset($user2['email'])) {
            $this->ajaxFailed('用户名已经被使用了');
        }
        unset($user, $user2);
        list($ret, $user) = $userModel->addUser($userInfo);
        if ($ret == UserModel::REG_RETURN_CODE_OK) {
            $updateInfo = [];
            if (isset($params['avatar']) && !empty($params['avatar'])) {
                $base64String = $params['avatar'];
                $saveRet = UploadLogic::base64ImageContent($base64String, PUBLIC_PATH . 'attachment/avatar/', $user['uid']);
                if ($saveRet !== false) {
                    $updateInfo['avatar'] = 'avatar/' . $saveRet . '?t=' . time();
                    $userModel->uid = $user['uid'];
                    $ret = $userModel->updateUser($updateInfo);
                }
                unset($params['avatar'], $base64String);
            } else {
                $defaultAvatar = UserLogic::makeDefaultAvatar($user['uid'], $user['display_name']);
                $userModel->updateUserById(['avatar' => $defaultAvatar['short_path']], $user['uid']);
            }

            if (isset($params['notify_email']) && $params['notify_email'] == '1') {
                $sysLogic = new SystemLogic();
                $content = "管理用户为您创建了Masterlab账号。<br>用户名：{$username}<br>密码：{$password}<br><br>请访问 " . ROOT_URL . " 进行登录<br>";
                $sysLogic->mail([$email], "Masterlab创建账号通知", $content);
            }
            $event = new CommonPlacedEvent($this, $user);
            $this->dispatcher->dispatch($event,  Events::onUserAddByAdmin);
            $this->ajaxSuccess('提示', '操作成功');
        } else {
            $this->ajaxFailed('服务器错误', "插入数据错误:" . $user);
        }
    }

    /**
     * @param $params
     * @throws \Exception
     */
    public function update($params)
    {
        $userId = $this->getParamUserId();
        $errorMsg = [];
        if (empty($params)) {
            $this->ajaxFailed('参数错误');
        }
        if (isset($params['display_name']) && empty($params['display_name'])) {
            $errorMsg['display_name'] = '请输入显示名称';
        }
        if (!empty($errorMsg)) {
            $this->ajaxFailed('参数错误', $errorMsg, BaseCtrl::AJAX_FAILED_TYPE_FORM_ERROR);
        }

        $info = [];
        if (isset($params['password']) && !empty($params['password'])) {
            $info['password'] = UserAuth::createPassword($params['password']);
        }
        if (isset($params['display_name'])) {
            $info['display_name'] = $params['display_name'];
        }
        if (isset($params['title'])) {
            $info['title'] = $params['title'];
        }
        if (isset($params['disable']) && (UserAuth::getId() != $userId)) {
            $info['status'] = UserModel::STATUS_DISABLED;
        } else {
            $info['status'] = UserModel::STATUS_NORMAL;
        }

        if (isset($params['avatar'])) {
            $base64String = $params['avatar'];
            $saveRet = UploadLogic::base64ImageContent($base64String, PUBLIC_PATH . 'attachment/avatar/', $userId);
            if ($saveRet !== false) {
                $info['avatar'] = 'avatar/' . $saveRet . '?t=' . time();
            }
            unset($params['avatar'], $base64String);
        }
        $userModel = UserModel::getInstance($userId);
        $userModel->uid = $userId;
        $userModel->updateUser($info);
        // userModel->updateById($userId, $info);

        // 分发事件
        $event = new CommonPlacedEvent($this, $info);
        $this->dispatcher->dispatch($event,  Events::onUserUpdateByAdmin);
        $this->ajaxSuccess('提示', '操作成功');
    }

    /**
     * 删除用户
     * @throws \Exception
     */
    public function delete($uid)
    {
        $userId = $this->getParamUserId();
        if (empty($uid)) {
            $this->ajaxFailed('no_uid');
        }
        if ($userId == UserAuth::getId()) {
            $this->ajaxFailed('逻辑错误', '不能自己');
        }
        // @todo 要处理删除后该用户关联的事项
        $userModel = new UserModel();
        $user = $userModel->getByUid($userId);
        if (empty($user)) {
            $this->ajaxFailed('参数错误', '用户不存在');
        }
        if ($user['is_system'] == '1') {
            $this->ajaxFailed('逻辑错误', '不能删除系统自带的用户');
        }

        $ret = $userModel->deleteById($userId);
        if (!$ret) {
            $this->ajaxFailed('系统错误', '删除用户失败,原因是数据库执行错误');
        } else {
            $userModel = new UserGroupModel();
            $userModel->deleteByUid($userId);
            // 分发事件
            $event = new CommonPlacedEvent($this, $user);
            $this->dispatcher->dispatch($event,  Events::onUserDeleteByAdmin);
            $this->ajaxSuccess('提示', '操作成功');
        }
    }

    /**
     * 批量删除帐户
     * @throws \Exception
     */
    public function batchDisable()
    {
        if (empty($_REQUEST['checkbox_id']) || !isset($_REQUEST['checkbox_id'])) {
            $this->ajaxFailed('no_request_uid');
        }
        $userIdArr = $_REQUEST['checkbox_id'];
        $userModel = UserModel::getInstance();
        foreach ($userIdArr as $uid) {
            $userModel->uid = intval($uid);
            $userInfo = [];
            $userInfo['status'] = UserModel::STATUS_DISABLED;
            list($ret, $msg) = $userModel->updateUser($userInfo);
            if (!$ret) {
                $this->ajaxFailed('server_error_update_failed:' . $msg);
            }
        }
        // 分发事件
        $event = new CommonPlacedEvent($this, $user=$userModel->getUsersByIds($userIdArr));
        $this->dispatcher->dispatch($event,  Events::onUserBatchDisableByAdmin);
        $this->ajaxSuccess('提示', '操作成功');
    }

    /**
     * 批量恢复帐户
     * @throws \Exception
     */
    public function batchRecovery()
    {
        if (empty($_REQUEST['checkbox_id']) || !isset($_REQUEST['checkbox_id'])) {
            $this->ajaxFailed('no_request_id');
        }
        $userIdArr = $_REQUEST['checkbox_id'];
        $userModel = UserModel::getInstance();
        foreach ($userIdArr as $id) {
            $userModel->uid = intval($id);
            $userInfo = [];
            $userInfo['status'] = UserModel::STATUS_NORMAL;
            $userModel->updateUser($userInfo);
        }
        // 分发事件
        $event = new CommonPlacedEvent($this, $user=$userModel->getUsersByIds($userIdArr));
        $this->dispatcher->dispatch($event,  Events::onUserBatchRecoveryByAdmin);
        $this->ajaxSuccess('提示', '操作成功');
    }

    /**
     * @param $email
     * @param $userId
     * @param $displayName
     * @param $verifyCode
     * @return array
     * @throws \Exception
     */
    private function sendVerifyEmail($email, $userId, $displayName,  $verifyCode)
    {
        $args = [];
        $args['{{site_name}}'] = 'Masterlab';
        $args['{{name}}'] = $displayName;
        $args['{{display_name}}'] = $displayName;
        $args['{{email}}'] = $email;
        $args['{{url}}'] = ROOT_URL . 'passport/verify_email?user_id=' . $userId . '&verify_code=' . $verifyCode;
        $body = str_replace(array_keys($args), array_values($args), getCommonConfigVar('mail_tpl')['tpl']['reg_verify_email']);
        // echo $body;die;
        $systemLogic = new SystemLogic();
        list($ret, $errMsg) = $systemLogic->mail($email, 'Masterlab验证邮箱通知', $body, $replyTo = [], $others = [], $checkEnableMail=false);
        //var_dump($ret, $errMsg);
        if (!$ret) {
            return [false, '发送邮件失败,请联系管理员:' . $errMsg];
        }
        return [true, 'ok'];
    }

    /**
     * 重发验证邮件
     * @throws \Exception
     */
    public function reSendVerifyEmail()
    {
        if (!isset($_POST['user_id']) ||  empty($_POST['user_id'])) {
            $this->ajaxFailed('参数错误', 'user_id为空');
        }
        $userId = $_POST['user_id'];
        $userModel = new UserModel();
        $user = $userModel->getByUid($userId);
        if(empty($user) || empty($user['email'])){
            $this->ajaxFailed('提示', '用户数据为空，请刷新页面');
        }
        if($user['is_verified']==1){
            $this->ajaxFailed('提示', '该用户邮箱已经验证过了');
        }
        if(isset($_SESSION['reSendVerifyEmailTime']) && $_SESSION['reSendVerifyEmailTime']>time()-60){
            $this->ajaxFailed('提示', '请1分钟后再重发');
        }
        $_SESSION['reSendVerifyEmailTime'] = time();
        $verifyCode = randString(12);
        $updateInfo['verify_code'] = $verifyCode;
        $userModel->updateUserById($updateInfo, $user['uid']);
        list($ret, $errMsg) = $this->sendVerifyEmail($user['uid'], $user['email'], $user['display_name'], $verifyCode);
        //var_dump($ret, $errMsg);
        if (!$ret) {
            $this->ajaxFailed('提示', $errMsg);
        }
        $this->ajaxSuccess('操作成功');
    }

}
