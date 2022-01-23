<?php
/**
 * Created by PhpStorm.
 */

namespace main\app\ctrl;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use main\app\classes\UserAuth;
use main\app\classes\UserLogic;
use main\app\classes\SystemLogic;
use main\app\event\Events;
use main\app\event\CommonPlacedEvent;
use main\app\model\Setting2Model;
use main\app\model\user\EmailFindPasswordModel;
use main\app\model\user\UserModel;
use main\app\model\SettingModel;
use main\app\model\user\UserTokenModel;
use main\app\model\user\LoginlogModel;
use main\app\model\user\EmailVerifyCodeModel;
use main\app\classes\SettingsLogic;

/**
 * 用户账号相关功能
 */
class Passport extends BaseCtrl
{

    /**
     * 登录状态保持对象
     * @var \main\app\classes\UserAuth;
     */
    protected $auth;

    /**
     * Passport constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->auth = UserAuth::getInstance();
    }

    /**
     * 刷新token
     * @param $refresh_token
     * @throws \Exception
     */
    public function refreshToken($refresh_token)
    {
        if (empty($refresh_token)) {
            $this->ajaxFailed('错误', '参数错误,必要参数不能为空');
        }
        $userTokenModel = new UserTokenModel();
        list(
            $validTokenRetCode,
            $validTokenRetMsg,
            $userToken
            ) = $userTokenModel->validRefreshToken($refresh_token);

        if ($validTokenRetCode == UserTokenModel::VALID_TOKEN_RET_EXPIRE) {
            $this->ajaxFailed($validTokenRetMsg, [], UserTokenModel::HTTP_RESPONSE_EXPIRE);
        }
        if ($validTokenRetCode == UserTokenModel::VALID_TOKEN_RET_NOT_EXIST) {
            $this->ajaxFailed($validTokenRetMsg, [], UserTokenModel::HTTP_RESPONSE_INVALID);
        }

        if ($validTokenRetCode == UserTokenModel::VALID_TOKEN_RET_OK) {
            $userModel = UserModel::getInstance($userToken['uid']);
            $user = $userModel->getByUid($userToken['uid']);

            list($ret, $token, $refresh_token) = $userTokenModel->makeToken($user);
            if (!$ret) {
                $this->ajaxFailed('服务器错误', '刷新token失败');
            }

            $final['token'] = $token;
            $final['refresh_token'] = $refresh_token;
            $tokenCfg = getConfigVar('data');
            $final['token_expire'] = intval($tokenCfg['token']['expire_time']);

            $this->ajaxSuccess('ok', $final, UserTokenModel::HTTP_RESPONSE_OK);
        }

        $this->ajaxFailed('refresh fail.');
    }

    /**
     * 登录页面
     * @throws \Exception
     */
    public function pageLogin()
    {
        $data = [];
        $data['title'] = '登录';
        $data['is_login_page'] = true;
        // 获取设置
        $settingModel = new SettingModel();
        $basicSettingArr = array_column($settingModel->getSettingByModule('basic'), '_value', '_key');
        $data['login_require_captcha'] = $basicSettingArr['login_require_captcha'];
        $data['reg_require_captcha'] = $basicSettingArr['reg_require_captcha'];
        $data['allow_user_reg'] = $basicSettingArr['allow_user_reg'];
        $data['ldap_enable'] = (bool)$settingModel->getSettingValue('ldap_enable');

        //print_r($_COOKIE);
        if (isset($_COOKIE[UserAuth::SESSION_UID_KEY]) && isset($_COOKIE[UserAuth::SESSION_TOKEN_KEY])) {
            $ret = $this->auth->autoLogin($_COOKIE[UserAuth::SESSION_UID_KEY], $_COOKIE[UserAuth::SESSION_TOKEN_KEY]);
            if ($ret) {
                header("location:" . ROOT_URL);
                die;
            }
        }
        $this->render('gitlab/passport/login.php', $data);
    }


    /**
     * @throws \Exception
     */
    public function pageJwtLogin()
    {
        error_reporting(E_ERROR);
        $jwt = $_GET['jwt'] ?? '';
        $signer = new Sha256();
        $key = new Key(JWT_KEY);
        try {
            $builder = new Builder();
            $parse = (new Parser())->parse($jwt);
            $headers = $parse->getHeaders();
            $builder = $builder->sign($signer, $key);
            $token = $builder->getToken();
            //验证token合法性
            // var_dump($token->verify($signer, JWT_KEY));
            if (!$token->verify($signer, JWT_KEY)) {
                $this->error('提示', '认证令牌不匹配');
                return;
            }
            // 是否过期
            if ($parse->isExpired()) {
                $this->error('提示', '认证令牌已经过期');
                return;
            }
            $platformUserId = $headers['uid'];
            $platformPhone = $headers['phone'];
            $jwtIp = $headers['ip'];
            $userIp = getIp();
            if($jwtIp!=$userIp){
                $this->error('提示', '您的IP地址已经变更');
                return;
            }
            $userModel = new UserModel();
            $user = $userModel->getByPhone($platformPhone);
            if(empty($user)){
                $user = $userModel->getByUsername($platformPhone);
            }
            if(empty($user)){
                $this->error('提示', "手机号码:{$platformPhone}已经失效了");
                return;
            }
            $cookieLifetime = getCommonConfigVar('session')['session.cookie_lifetime'];
            $this->auth->login($user, $cookieLifetime);
            header('location: /');

        } catch (\Exception $e) {
            $result['msg'] = 'Invalid token.';
            $result['available'] = false;
            $this->error('提示', '认证令牌异常:'.$e->getMessage());
            return;
        }
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function pageTest()
    {
        $data = [];
        $data['title'] = '测试Dbal';

        $id = 10;
        $dbalModel = new Setting2Model();
        $sql = "SELECT * FROM main_setting WHERE id > ?";
        $stmt = $dbalModel->db->prepare($sql);
        $stmt->bindValue(1, $id);
        $stmt->execute();
        // $settings = $stmt->fetchAll();
        $table = str_replace("`",'',$dbalModel->getTable());
        $sql = "SHOW TABLES LIKE  '" . $table. "'";
        echo $sql;
        $tableName = $dbalModel->db->fetchColumn($sql);
        var_dump($tableName);

        $paramArr = [];
        $paramArr = ['username'=>'master'];
        $user =  $dbalModel->db->fetchAssoc('SELECT * FROM user_main WHERE username =:username', $paramArr);
        print_r($user);

        //$queryBuilder = $dbalModel->db->createQueryBuilder();

    }

    /**
     * 输出验证码
     */
    public function pageOutputCaptcha($mode)
    {
        if (!in_array($mode, array('login', 'reg'))) {
            $mode = 'login';
        }
        $builder = new \Gregwar\Captcha\CaptchaBuilder;
        $builder->build(150, 40);
        if ($mode == 'login') {
            $_SESSION['login_captcha'] = $builder->getPhrase();
            $_SESSION['login_captcha_time'] = time();
        }
        if ($mode == 'reg') {
            $_SESSION['reg_captcha'] = $builder->getPhrase();
            $_SESSION['reg_captcha_time'] = time();
        }
        echo $builder->inline();
    }

    /**
     * 注销
     */
    public function pageLogout()
    {
        UserAuth::getInstance()->logout();
        $this->pagelogin();
		$event = new CommonPlacedEvent($this, UserModel::getInstance()->getByUid(UserAuth::getId()));
        $this->dispatcher->dispatch($event,  Events::onUserlogout);

        @header("location: /passport/login");
        die;
    }

    /**
     * 登录
     * @param string $username
     * @param string $password
     * @param string $ios_token
     * @param string $android_token
     * @param string $version
     * @param string $openid
     * @param string $display_name
     * @param string $headimgurl
     * @param int $source
     * @throws \Exception
     */
    public function doLogin(
        $username = '',
        $password = '',
        $ios_token = '',
        $android_token = '',
        $version = '',
        $openid = '',
        $display_name = '',
        $headimgurl = '',
        $source = 0
    )
    {
        //print_r($GLOBALS['framework']);
        // var_dump(func_get_args());
        if (empty($username)) {
            $this->ajaxFailed('错误', '参数错误,username 不能为空');
        }

        if (empty($password)) {
            $this->ajaxFailed('错误', '参数错误,password 不能为空');
        }

        $userModel = UserModel::getInstance('');
        $final = [];
        $final['user'] = new \stdClass();
        $final['msg'] = '';
        $final['code'] = 0;
        // sleep( 5 );
        // 使用对称aes加密解密
        if (isset($_POST["aes_json"]) && isset($_POST["passphrase"])) {
            $passPhrase = getConfigVar('data')['login']['pass_phrase'];
            $password = cryptoJsAesDecrypt($passPhrase, $_POST["aes_json"]);
            //$final['$password'] = $password;
        }
        // $err = [];
        // 检查登录错误次数,一个ip的登录错误次数限制
        $times = 0;
        $settingModel = SettingModel::getInstance();
        $muchErrTimesCaptcha = $settingModel->getSettingValue('muchErrorTimesCaptcha');
        $ipAddress = getIp();
        $reqVerifyCode = isset($_REQUEST['vcode']) ? strtolower($_REQUEST['vcode']) : false;
        $loginRequireCaptcha = SettingModel::getInstance()->getSettingValue('login_require_captcha');
        if($loginRequireCaptcha=='1'){
            if($reqVerifyCode===false || $reqVerifyCode!=strtolower($_SESSION['login_captcha'])){
                $this->ajaxFailed('提 示', ['vcode'=>'验证码错误'], parent::AJAX_FAILED_TYPE_FORM_ERROR);
            }
        }
        $arr = $this->auth->checkIpErrorTimes($reqVerifyCode, $ipAddress, $times, $muchErrTimesCaptcha);
        list($ret, $retCode, $tip) = $arr;
        if (!$ret) {
            $this->ajaxFailed('提 示', $tip, $retCode);
        }

        $schemaType = 'inner';
        if (isset($_POST['schema_ldap'])) {
            $schemaType = 'ldap';
        }
        // 检查登录账号和密码
        if ($schemaType === 'inner') {
            list($ret, $user) = $this->auth->checkLoginByUsername($username, $password);
        } else {
            // LDAP认证登录
            list($ret, $user) = $this->auth->checkLdapByUsername($username, $password);
        }
        // print_r($user);
        if ($ret != UserModel::LOGIN_CODE_OK) {
            $code = intval($ret);
            $tip = '密码错误';
            $arr = $this->auth->checkRequireLoginVCode($ipAddress, $times, $muchErrTimesCaptcha);
            list($ret2, $code2) = $arr;
            if (!$ret2) {
                $code = $code2;
                $tip = '错误太多,需要输入验证码';//$arr['msg'];
            }
            $this->ajaxFailed('提 示', $tip, $code);
        }

        unset($_SESSION['login_captcha'], $_SESSION['login_captcha_time']);

        // 更新登录次数
        $this->auth->updateIpLoginTime($times, $muchErrTimesCaptcha);

        if (intval($user['status']) == UserModel::STATUS_PENDING_APPROVAL) {
            $this->ajaxFailed('提 示', '该用户尚未激活');
        }

        if ($user['status'] != UserModel::STATUS_NORMAL) {
            $this->ajaxFailed('提 示', '该用户已经被禁用');
        }
        if ($openid) {
            $info['weixin_openid'] = $openid;
            if (!$user['avatar']) {
                $info['avatar'] = $headimgurl;
            }
            $info['weixin_openid'] = $openid;
            $userModel->updateUserById($info, $user['uid']);
        }
        if ($ios_token) {
            $info['ios_token'] = $ios_token;
        }
        if ($android_token) {
            $info['android_token'] = $android_token;
        }
        if ($version) {
            $info['version'] = $version;
        }
        if ($display_name) {
            $info['display_name'] = $display_name;
        }
        if ($source) {
            $info['source'] = $source;
        }

        $this->auth->setAutoLogin($user);

        // 更新登录信息
        $userModel->uid = $user['uid'];
        $this->updateLoginInfo($userModel->uid);
        // 处理登录返回值
        $this->processLoginReturn($final, $user);
        // 记录登录日志,用于只允许单个用户登录
        $loginLogModel = new LoginlogModel();
        $loginLogModel->loginLogInsert($user['uid']);
        //$this->auth->kickCurrentUserOtherLogin($user['uid']);
        @setcookie('check_browser_flag', '', time() + 3600 * 4, '/', getCookieHost());
        // 分发事件
        $event = new CommonPlacedEvent($this, $user);
        $this->dispatcher->dispatch($event,  Events::onUserLogin);

        $this->ajaxSuccess($final['msg'], $final);
    }

    /**
     * 处理登录返回值
     * @param $final
     * @param $user
     * @throws \Exception
     */
    private function processLoginReturn(&$final, $user)
    {
        $userTokenModel = new UserTokenModel($user['uid']);
        $userTokenInfo = $userTokenModel->getUserToken($user['uid']);

        // 允许同一账户同时登陆(pc、app同时在线)，不再签发新的token
        if ($userTokenModel->isTokenExpire(@$userTokenInfo['token_time'])) {
            // 生成和刷新token
            list($ret, $token, $refresh_token) = $userTokenModel->makeToken($user);
            if (!$ret) {
                $this->ajaxFailed('服务器错误', '刷新token失败');
            }
        } else {
            $token = $userTokenInfo['token'];
            $refresh_token = $userTokenInfo['refresh_token'];
        }

        $final['token'] = $token;
        $final['refresh_token'] = $refresh_token;
        $tokenCfg = getConfigVar('data');
        $final['token_expire'] = intval($tokenCfg['token']['expire_time']);

        if (isset($user['password'])) {
            unset($user['password']);
        }
        // $_SESSION[UserAuth::SESSION_UID_KEY] = $user['uid'];
        $cookieLifetime = getCommonConfigVar('session')['session.cookie_lifetime'];
        $this->auth->login($user, $cookieLifetime);
        $_SESSION['user_info'] = $user;

        $userLogic = new UserLogic();
        $final['user'] = $userLogic->formatUserInfo($user);

        $final['code'] = UserModel::LOGIN_CODE_OK;
        $final['msg'] = '亲,登录成功';
    }

    /**
     * 更新登录信息
     * @param $uid
     * @throws \Exception
     */
    private function updateLoginInfo($uid)
    {
        $updateInfo = array();
        if (isset($_REQUEST['ios_token']) && !empty($_REQUEST['ios_token'])) {
            $updateInfo['ios_token'] = str_replace(array(" ", '<', '>'), array('', '', ''), $_REQUEST['ios_token']);
        }

        if (isset($_REQUEST['android_token']) && !empty($_REQUEST['android_token'])) {
            $updateInfo['android_token'] = str_replace(
                array(" ", '<', '>'),
                array('', '', ''),
                $_REQUEST['android_token']
            );
        }
        $updateInfo['last_login_time'] = time();
        if (!empty($updateInfo)) {
            $userModel = UserModel::getInstance($uid);
            $userModel->updateUser($updateInfo);
            unset($updateInfo);
        }
    }

    /**
     * 注销接口
     * @throws \Exception
     */
    public function doLogout()
    {
        //清除会话
        UserAuth::getInstance()->logout();
        $this->ajaxSuccess('ok');
    }


    /**
     * 邮箱注册注册
     * @throws \Exception
     */
    public function register()
    {
        // 是否开启用户注册
        $isAllowReg = (new SettingsLogic())->allowUserReg();
        if (!$isAllowReg) {
            $this->ajaxFailed('注册功能不可用', [], 500);
        }

        //参数检查
        $settingModel = new SettingModel();

        $err = [];
        // 是否需要图形验证码
        if ($settingModel->getSettingValue('reg_require_pic_code')) {
            $captchaCode = $_POST['captcha_code'];
            if (empty($captchaCode)) {
                $err['captcha_code'] = '图形验证码为空';
            }
            if (isset($_SESSION['reg_captcha'])
                && $captchaCode !== $_SESSION['reg_captcha']
                && (time() - $_SESSION['reg_captcha_time']) > 300) {
                $this->ajaxFailed('错误', '图形验证码不正确');
                $err['captcha_code'] = '图形验证码不正确';
            }
            if (isset($_SESSION['reg_captcha'])) {
                unset($_SESSION['reg_captcha']);
            }
            if (isset($_SESSION['reg_captcha_time'])) {
                unset($_SESSION['reg_captcha_time']);
            }
        }

        $passwordStrategy = $settingModel->getSettingValue('password_strategy');
        //var_dump($passwordStrategy);
        if ($passwordStrategy == 2) {
            // 密码需要6位及以上，并且包含大写字母、小写字母、数字至少两种
            // $pattern = '/^(?=.{6,})(((?=.*[A-Z])(?=.*[a-z]))|((?=.*[A-Z])(?=.*[0-9]))|((?=.*[a-z])(?=.*[0-9]))).*$/';
            // 密码需要6位及以上
            $pattern = '/(?=.{6,}).*/';
            if (!preg_match($pattern, $_POST['password'])) {
                $err['password'] = '密码需要6位及以上';
            }
        } elseif ($passwordStrategy == 3) {
            // 密码要求8位及以上，并且包含大写字母、小写字母、数字和特殊字符
            $pattern = '/^(?=.{8,})(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(?=.*\W).*$/';
            if (!preg_match($pattern, $_POST['password'])) {
                $err['password'] = '密码要求8位及以上，并且包含大写字母、小写字母、数字和特殊字符';
            }
        }

        if (!isset($_POST['email']) || empty($_POST['email'])) {
            $err['email'] = 'email不能为空';
        }
        if (!isset($_POST['email_confirmation']) || empty($_POST['email_confirmation'])) {
            $err['email_confirmation'] = '确认email不能为空';
        }
        if ($_POST['email'] != $_POST['email_confirmation']) {
            $err['email_confirmation'] = '两次email输入不一致';
        }
        if (!isset($_POST['username']) || empty($_POST['username'])) {
            $err['username'] = '用户名不能为空';
        }
        if (!isset($_POST['password']) || empty($_POST['password'])) {
            $err['password'] = '密码不能为空';
        }
        if (!isset($_POST['display_name']) || empty($_POST['display_name'])) {
            $err['display_name'] = '显示名称不能为空';
        }
        $username = trimStr($_POST['username']);
        $email = trimStr($_POST['email']);
        $password = trimStr($_POST['password']);
        $displayName = trimStr(safeStr($_POST['display_name']));
        $avatar = isset($_POST['avatar']) ? safeStr($_POST['avatar']) : "";
        if (strlen($password) > 40) {
            $err['password'] = '密码长度太长了';
        }
        // 检查参数是否正确
        if (!empty($err)) {
            $this->ajaxFailed('参数错误,请检查', $err, BaseCtrl::AJAX_FAILED_TYPE_FORM_ERROR);
        }

        // 检查用户名和email是否可用
        $err = [];
        $userModel = UserModel::getInstance('');
        $user = $userModel->getByEmail($email);
        if (isset($user['uid'])) {
            $err['email'] = 'email已经被使用了';
        }
        $user = $userModel->getByUsername($username);
        if (isset($user['uid'])) {
            $err['username'] = '用户名已经被使用了';
        }
        unset($user);
        if (!empty($err)) {
            $this->ajaxFailed('参数错误,请检查', $err, BaseCtrl::AJAX_FAILED_TYPE_FORM_ERROR);
        }

        $userInfo = [];
        $userInfo['password'] = UserAuth::createPassword($password);
        $userInfo['email'] = safeStr($email);
        $userInfo['username'] = safeStr($username);
        $userInfo['display_name'] = safeStr($displayName);
        $userInfo['status'] = UserModel::STATUS_PENDING_APPROVAL;
        $userInfo['create_time'] = time();
        $userInfo['avatar'] = $avatar;
        $userInfo['is_verified'] = '0';
        $userModel = new UserModel();
        list($ret, $user) = $userModel->addUser($userInfo);
        if ($ret == UserModel::REG_RETURN_CODE_OK) {
            // 生成默认头像
            if (empty($avatar)) {
                if (empty($user['display_name'])) {
                    $avatarName = $userInfo['username'];
                } else {
                    $avatarName = $userInfo['display_name'];
                }
                $defaultAvatar = UserLogic::makeDefaultAvatar($user['uid'], $avatarName);
                $userModel->updateUserById(['avatar' => $defaultAvatar['short_path']], $user['uid']);
            }
			// 分发事件
            $event = new CommonPlacedEvent($this, $user);
            $this->dispatcher->dispatch($event,  Events::onUserRegister);

            list($mailRet, $msg) = $this->sendActiveEmail($user, $email, $displayName);
            if ($mailRet) {
                $this->ajaxSuccess('提示', '注册已经提交，请查看邮箱的激活邮件');
            } else {
                $this->ajaxSuccess('提示', '但发送激活邮件失效，请联系管理手动激活');
            }
        } else {
            $this->ajaxFailed('服务器错误', '注册失败,详情:' . $user);
        }
    }


    /**
     * 重新发送注册激活邮件
     * @throws \Exception
     */
    public function reSendActiveEmail()
    {
        $email = null;
        if (isset($_POST['email'])) {
            $email = $_POST['email'];
        }
        $userName = null;
        if (isset($_POST['username'])) {
            $userName = $_POST['username'];
        }
        if (empty($email)) {
            $this->ajaxFailed('提示', '参数错误, cookie失效，注册的Email已经丢失，请联系管理员手动激活用户');
        }

        $emailVerifyCodeModel = new EmailVerifyCodeModel();
        $row = $emailVerifyCodeModel->getByEmail($email);
        if (!isset($row['email'])) {
            $this->ajaxFailed('提示', '注册Email不存在，您的账号可能已经激活了');
        }
        if (time() - intval($row['time']) < 10) {
            $this->ajaxFailed('提示', '请10秒后再点击重新发送');
        }
        $verifyCode = $row['verify_code'];
        $userModel = new UserModel();
        $user = $userModel->getByUsername($userName);
        if (empty($user)) {
            $user = $userModel->getByEmail($email);
        }
        $emailVerifyCodeModel->updateById($row['id'], ['time' => time()]);
        list($ret, $msg) = $this->sendActiveEmailByVerifyCode($user, $email, $verifyCode);
        if (!$ret) {
            $this->ajaxFailed('提示', $msg);
        }
        $this->ajaxSuccess('提示', '已经重新发送,请耐心查看收件箱');
    }

    /**
     * 发送邮箱待激活的email
     * @param $user
     * @param string $email
     * @param string $username
     * @return array
     * @throws \Exception
     */
    private function sendActiveEmail($user, $email = '', $username = '')
    {
        $verifyCode = randString(32);
        $emailVerifyCodeModel = new EmailVerifyCodeModel();
        $row = $emailVerifyCodeModel->getByEmail($email);
        if (isset($row['email'])) {
            if (time() - intval($row['time']) < 30) {
                return [false, '请30秒后再点击重新发送'];
            }
        }
        list($flag, $insertId) = $emailVerifyCodeModel->add($user['uid'], $email, $username, $verifyCode);
        if ($flag && APP_STATUS != 'travis') {
            return $this->sendActiveEmailByVerifyCode($user, $email, $verifyCode);
        } else {
            //'很抱歉,服务器繁忙，请重试!!';
            return [false, '服务器错误:' . $insertId];
        }
    }

    /**
     * @param $user
     * @param $email
     * @param $verifyCode
     * @return array
     * @throws \Exception
     */
    private function sendActiveEmailByVerifyCode($user, $email, $verifyCode)
    {
        $args = [];
        $args['{{site_name}}'] = 'Masterlab';
        $args['{{name}}'] = $user['display_name'];
        $args['{{display_name}}'] = $user['display_name'];
        $args['{{email}}'] = $email;
        $args['{{url}}'] = ROOT_URL . 'passport/active_email?email=' . $email . '&verify_code=' . $verifyCode;
        $body = str_replace(array_keys($args), array_values($args), getCommonConfigVar('mail_tpl')['tpl']['active_email']);
        // echo $body;die;
        $systemLogic = new SystemLogic();
        list($ret, $errMsg) = $systemLogic->mail($email, 'Masterlab激活用户通知', $body);
        //var_dump($ret, $errMsg);
        if (!$ret) {
            return [false, '发送邮件失败,请联系管理员:' . $errMsg];
        }
        return [true, 'ok'];
    }

    /**
     * 打开邮箱,激活用户
     * @throws \Exception
     */
    public function pageVerifyEmail()
    {
        if (!isset($_GET['user_id'])) {
            $this->error('参数错误', '参数错误,user_id缺失');
            return;
        }
        if (!isset($_GET['verify_code'])) {
            $this->error('参数错误', '参数错误,verify_code缺失');
            return;
        }
        $userId = intval($_GET['user_id']);
        $verifyCode = trimStr($_GET['verify_code']);

        $userModel = UserModel::getInstance($userId);
        $user = $userModel->getByUid($userId);
        if (!isset($user['uid'])) {
            $this->error('错误信息', '参数错误');
            return;
        }
        if ($user['is_verified']=='1') {
            $this->info('提示', '亲,该邮箱已经验证过了');
            return;
        }
        if ( $verifyCode != $user['verify_code']) {
            $this->error('错误信息', '亲,激活链接已经失效或已经被激活过了');
            return;
        }
        //参数检查
        $userInfo = [];
        $userInfo['is_verified'] = '1';
        list($ret, $msg) = $userModel->updateById($userId, $userInfo);
        if ($ret) {
            $this->info('信息提示', '验证邮箱成功!');
        } else {
            $this->info('信息提示', '验证邮箱失败:' . $msg);
        }
    }


    /**
     * 打开邮箱,激活用户
     * @throws \Exception
     */
    public function pageActiveEmail()
    {
        if (!isset($_GET['email'])) {
            $this->error('参数错误', 'email_param_error');
            return;
        }
        if (!isset($_GET['verify_code'])) {
            $this->error('参数错误', 'verify_code_param_error');
            return;
        }
        $email = trimStr($_GET['email']);
        $verifyCode = trimStr($_GET['verify_code']);

        $userModel = UserModel::getInstance('');
        $user = $userModel->getByEmail($email);
        if (!isset($user['email'])) {
            $this->error('错误信息', 'email_exist');
            return;
        }
        unset($user);

        // 校验验证码
        $emailVerifyCodeModel = new EmailVerifyCodeModel();
        $find = $emailVerifyCodeModel->getByEmailVerify($email, $verifyCode);

        if (!isset($find['email']) || $verifyCode != $find['verify_code']) {
            $this->error('错误信息', '亲,激活链接已经失效或已经被激活过了');
            return;
        }

        if ((time() - (int)$find['time']) > 7 * 24 * 3600) {
            $this->error('错误信息', '亲,激活链接时间已经失效');
            return;
        }

        //参数检查
        $userInfo = [];
        $userInfo['status'] = UserModel::STATUS_NORMAL;
        $userInfo['is_verified'] = '1';
        // $userInfo['email'] = $find['email'];
        // $userInfo['username'] = $find['username'];
        $userId = $find['uid'];
        list($ret, $msg) = $userModel->updateById($userId, $userInfo);
        if ($ret) {
            $emailVerifyCodeModel->deleteByEmail($email);
            $this->info('信息提示', '激活账号成功!');
        } else {
            $this->info('信息提示', '激活账号失败:' . $msg);
        }
    }

    /**
     * @throws \Exception
     */
    public function pageFindPassword()
    {
        $this->render('gitlab/passport/find_password.php');
    }

    /**
     * 发送找回密码
     * @throws \Exception
     */
    public function sendFindPasswordEmail()
    {
        $email = null;
        if (isset($_REQUEST['email'])) {
            $email = $_REQUEST['email'];
        }
        if (empty($email)) {
            $this->ajaxFailed('提示', '参数错误,email 不能为空', BaseCtrl::AJAX_FAILED_TYPE_TIP);
        }
        $userModel = UserModel::getInstance();
        $user = $userModel->getByEmail($email);
        if (!isset($user['uid'])) {
            $this->ajaxFailed('提示', 'email 地址不存在', BaseCtrl::AJAX_FAILED_TYPE_TIP);
        }
        $verifyCode = randString(32);
        $emailFindPwdModel = new EmailFindPasswordModel();

        $row = $emailFindPwdModel->getByEmail($email);
        if (isset($row['email'])) {
            if (time() - intval($row['time']) < 59) {
                //$this->ajaxFailed('提示', '操作过于频繁请稍后再发送', BaseCtrl::AJAX_FAILED_TYPE_TIP);
            }
        }
        list($flag, $insertId) = $emailFindPwdModel->add($email, $verifyCode);
        if ($flag && APP_STATUS != 'travis') {
            $args = [];
            $args['{{site_name}}'] = (new SettingsLogic())->showSysTitle();
            $args['{{name}}'] = $user['display_name'];
            $args['{{email}}'] = $email;
            $args['{{verifyCode}}'] = $verifyCode;
            $url = ROOT_URL . 'passport/display_reset_password?email=' . $email . '&verify_code=' . $verifyCode;
            $args['{{url}}'] = $url;
            $body = str_replace(array_keys($args), array_values($args), getCommonConfigVar('mail_tpl')['tpl']['reset_password']);
            // echo strlen($body);
            //@TODO 异步发送
            $systemLogic = new SystemLogic();
            list($ret, $errMsg) = $systemLogic->mail($email, '找回密码邮箱通知', $body);
            if (!$ret) {
                $this->ajaxFailed('服务器错误', '发送邮件失败,请求:' . $errMsg);
            }
        } else {
            //'很抱歉,服务器繁忙，请重试!!';
            $this->ajaxFailed('服务器错误', '插入失败,详情:' . $insertId);
        }
        $this->ajaxSuccess('邮件已发送');
    }


    public function pageDisplayResetPassword()
    {
        if (!isset($_GET['email'])) {
            $this->error('参数错误', '邮件地址为空');
            return;
        }
        if (!isset($_GET['verify_code'])) {
            $this->error('参数错误', '验证码为空');
            return;
        }
        $email = trimStr($_GET['email']);
        $verifyCode = trimStr($_GET['verify_code']);

        // 校验验证码
        $emailFindPwdModel = new EmailFindPasswordModel();
        $find = $emailFindPwdModel->getByEmailVerifyCode($email, $verifyCode);

        if (!isset($find['email']) || $verifyCode != $find['verify_code']) {
            $this->error('错误信息', '亲,激活链接已经失效,请重试');
            return;
        }
        if ((time() - (int)$find['time']) > 36000) {
            $this->error('错误信息', '亲,激活链接时间已经失效');
            return;
        }
        $data = ['email' => $email, 'verify_code' => $verifyCode];
        $this->render('gitlab/passport/reset_password.php', $data);
    }

    /**
     * 处理重置密码
     * @throws \Exception
     */
    public function pageResetPassword()
    {
        if (!isset($_POST['email'])) {
            $this->error('参数错误', '邮件地址为空');
            return;
        }
        if (!isset($_POST['verify_code'])) {
            $this->error('参数错误', '验证码为空');
            return;
        }
        if (!isset($_POST['password'])) {
            $this->error('参数错误', '密码为空');
            return;
        }
        if (!isset($_POST['password_confirmation'])) {
            $this->error('参数错误', '确认密码为空');
            return;
        }
        $email = trimStr($_POST['email']);
        $verifyCode = trimStr($_POST['verify_code']);
        $password = trimStr($_POST['password']);
        $passwordConfirmation = trimStr($_POST['password_confirmation']);

        $userModel = UserModel::getInstance('');
        $user = $userModel->getByEmail($email);
        if (!isset($user['email'])) {
            $this->error('参数错误', '邮件不存在');
            return;
        }
        // 校验验证码
        $emailFindPwdModel = new EmailFindPasswordModel();
        $find = $emailFindPwdModel->getByEmailVerifyCode($email, $verifyCode);

        if (!isset($find['email']) || $verifyCode != $find['verify_code']) {
            $this->error('信息提示', '亲,此链接已经失效');
            return;
        }

        if ((time() - (int)$find['time']) > (3600 * 24)) {
            $this->error('信息提示', '亲,此链接时间已经失效');
            return;
        }
        if ($password != $passwordConfirmation) {
            $this->error('信息提示', '两次密码输入不一致');
            return;
        }

        $userInfo = [];
        $userInfo['password'] = UserAuth::createPassword($password);
        $userModel->uid = $user['uid'];
        list($ret, $msg) = $userModel->updateUser($userInfo);
        if ($ret) {
            $emailFindPwdModel->deleteByEmail($email);
            $arr = [];
            $arr['_title'] = '提示';
            $arr['_links'] = ['type' => 'link', 'link' => '/passport/login', 'title' => '重新登录'];
            $arr['_content'] = '重置密码成功!';
            $arr['_icon'] = 'icon-font-ok';
            $arr['_color_type'] = 'alert-success';
            $this->render('twig/common/info_reset_password.twig', $arr);
        } else {
            $this->info('信息提示', '很抱歉,重置密码失败,请重试.' . $msg);
        }
    }


    /**
     * 检查邮箱是否存在
     * @param string $email
     * @throws \Exception
     */
    public function emailExist($email = '')
    {
        if (empty($email)) {
            $this->ajaxFailed('email地址不能为空', false);
        }
        $userModel = UserModel::getInstance();
        $user = $userModel->getByEmail($email);
        if (isset($user['uid'])) {
            $this->ajaxFailed('email已被使用', false);
        } else {
            $this->ajaxSuccess('ok', true);
        }
    }

    /**
     * 检查用户名你是否存在
     * @param string $username
     * @throws \Exception
     */
    public function usernameExist($username = '')
    {
        if (empty($username)) {
            $this->ajaxFailed('用户名不能为空', false);
        }
        $userModel = UserModel::getInstance();
        $user = $userModel->getByUsername($username);
        if (isset($user['uid'])) {
            $this->ajaxFailed('用户名已被使用', false);
        } else {
            $this->ajaxSuccess('ok', true);
        }
    }
}
