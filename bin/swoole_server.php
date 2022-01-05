<?php

require_once realpath(dirname(__FILE__)) . '/bootstrap.php';
require_once $rootDir . '/vendor/autoload.php';

use main\app\classes\UserAuth;
use main\app\model\DbModel;
use main\app\model\SettingModel;
use main\app\model\system\MailQueueModel;
use main\plugin\webhook\model\WebHookLogModel;

$socketConfig = $GLOBALS['_yml_config']['socket'];
print_r($socketConfig);

if ($socketConfig) {
    $socketHost = trimStr($socketConfig['host']);
    $socketPort = (int)$socketConfig['port'];
} else {
    $socketHost = trimStr($config['socket_server_host']);
    $socketPort = (int)$config['socket_server_port'];
}
$action = 'start';
if (isset($argv[1]) && !empty($argv[1])) {
    $action = trimStr($argv[1]);
}
$daemonize = false;
if ($action == 'daemon') {
    $daemonize = true;
    $action = 'start';
}
var_dump($action, $daemonize);
if ($action != 'start') {
    sendActionToServer($action);
    die;
}

$server = new Swoole\Server($socketHost, $socketPort);
//设置异步任务的工作进程数量
$server->set(array('task_worker_num' => 8));

$server->set([
    'daemonize' => $daemonize, //是否作为守护进程
    'open_length_check' => true,
    'package_max_length' => 1024 * 1024 * 10,
    'package_length_type' => 'N', //see php pack()
    'package_length_offset' => 0,
    'package_body_offset' => 4,
]);
//此回调函数在worker进程中执行
$server->on('receive', function ($serv, $fd, $from_id, $data) {
    $len = unpack('N', $data)['1'];
    $body = substr($data, -$len);
    //echo $body;
    //投递异步任务
    $task_id = $serv->task($body);
    echo "Dispatch AsyncTask:id=$task_id\n";
});
//处理异步任务(此回调函数在task进程中执行)
$server->on('task', function ($serv, $task_id, $from_id, $data) {
    global $socketPort;
    echo "New AsyncTask[id=$task_id]".PHP_EOL;
    $bodyArr = json_decode($data, true);
    $sendArr = $bodyArr;
    $cmd = strtolower($sendArr['cmd']) ;
    echo "Cmd: {$cmd}".PHP_EOL;
    if ($cmd == 'stop') {
        echo "Server shutdown\n";
        shutdownServer($serv);
    }
    if ($cmd == 'reload') {
        echo "Server reload\n";
        $serv->reload();
    }
    if ($cmd == 'mail') {
        echo "Send mail started \n";
        sendMail($serv, $sendArr);
    }
    if ($cmd == 'webhookpost') {
        echo "webhook post  started \n";
        print_r($sendArr);
        webhookPost($serv, $sendArr);
    }
    //
    $serv->finish("cmd not in array");
});

//处理异步任务的结果(此回调函数在worker进程中执行)
$server->on('finish', function ($serv, $task_id, $data) {
    echo "AsyncTask[$task_id] Finished " . PHP_EOL;
});
// 启动后启动定时任务检查
$server->on('start', function ($serv) {
    $serv->tick(1000, function () {
        //echo "crontab checking \n";
        $rootDir = realpath(dirname(__FILE__) . '/../');
        $json = file_get_contents($rootDir . '/bin/cron.json');
        $cronArr = json_decode($json, true);
        if (!$cronArr['schedule']) {
            return;
        }
        foreach ($cronArr['schedule'] as $item) {
            $exp = substr($item['exp'], 2);
            $exp = str_replace('?', '*', $exp);
            //echo $exp . " " . PHP_EOL;
            try {
                $cron = Cron\CronExpression::factory($exp);
                //echo $cron->getNextRunDate()->format('Y-m-d H:i:s')." \n";
                $runTime = $cron->getNextRunDate()->getTimestamp();
                $offsetTime = $runTime - time();
                if ($offsetTime < 2 && $offsetTime > -2) {
                    log_cron("脚本:" . $item['name'] . " " . $item['file'] . " 触发");
                    echo $item['name'] . ' ' . $cron->getNextRunDate()->format('Y-m-d H:i:s') . " \n";
                    // 达到时间要求
                    $cronPhpBin = $item['exe_bin'];
                    if (!file_exists($cronPhpBin)) {
                        list($phpBinRet, $phpBin) = get_php_bin_dir();
                        if (!$phpBinRet) {
                            continue;
                        }
                        $cronPhpBin = $phpBin;
                    }
                    $execFile = $item['file'];
                    $pathParts = pathinfo($execFile);
                    if (!file_exists($execFile)) {
                        $newFile = $rootDir . '/app/server/timer/' . $pathParts['basename'];
                        if (file_exists($newFile)) {
                            $execFile = $newFile;
                        }
                    }
                    exec("ps aux | grep " . $pathParts['basename'], $output, $return);
                    if ($return == 0) {
                        echo $item['file'] . ", process is running\n";
                        continue;
                    }
                    $command = $cronPhpBin . ' ' . $item['arg'] . ' ' . $execFile;
                    log_cron($command);
                    exec($command, $output);
                    log_cron(print_r($output, true));
                    log_cron("脚本:" . $item['name'] . " " . $execFile . " 执行结束");
                }
            } catch (\Exception $e) {
                echo $e->getMessage() . PHP_EOL;
            }

        }

    });
});


$server->start();

/**
 * 给服务器发送指令
 * @param $cmd
 * @return array
 */
function sendActionToServer($cmd)
{
    global $socketHost, $socketPort;
    $sendArr = [];
    $sendArr['cmd'] = $cmd;
    $sendArr['seq'] = 1;
    $sendArr['sid'] = 1;
    $sendArr['token'] = '';
    $sendArr['ver'] = '1.0';
    $body = json_encode($sendArr) . PHP_EOL;
    $fp = @fsockopen($socketHost, $socketPort, $errno, $errstr, 10);
    if (!$fp) {
        $err = 'fsockopen failed:' . mb_convert_encoding($errno . ' ' . $errstr, "UTF-8", "GBK");
        return [false, $err];
    }
    $packge = pack('N', strlen($body)) . $body;
    //echo $packge;
    fwrite($fp, $packge);
    fclose($fp);
    return [true, 'send finish'];
}

/**
 * 关闭服务
 * @param $serv
 */
function shutdownServer($serv)
{
    global $socketPort;
    $serv->stop();
    $serv->shutdown();
    $os = strtoupper(substr(PHP_OS, 0, 3));
    if ($os == 'WIN' || $os == 'CYG') {
        $pid = null;
        exec('netstat -ano | findstr "' . $socketPort . '"', $retArr);
        foreach ($retArr as $item) {
            $itemArr = preg_split("/[\s,]+/", $item);
            if (is_array($itemArr) && in_array('LISTENING', $itemArr)) {
                $pid = end($itemArr);
                break;
            }
        }
        if($pid){
            $killCmd = 'taskkill /f /t /im "' . $pid . '"';
            echo $killCmd . "\n";
            exec($killCmd, $retArr);
            print_r($retArr);
        }
    } else {
        // system("ps -ef |grep swoole_server.php |awk '{print $2}'|xargs kill -9", $exeRetLines);
        $pid = null;
        exec('netstat -nap | grep ' . $socketPort , $retArr);
        foreach ($retArr as $item) {
            $itemArr = preg_split("/[\s,]+/", $item);
            if (is_array($itemArr) && in_array('LISTEN', $itemArr)) {
                $end = end($itemArr);
                list($pid) = explode('/', $end);
                break;
            }
        }
        if($pid){
            $killCmd =  "kill -9  {$pid} ";
            echo $killCmd . "\n";
            exec($killCmd, $retArr);
            var_dump($exeRetLines);
        }
    }
    $serv->finish("shutdown OK");
}

/**
 * 执行发送邮件
 * @param $serv
 * @param $sendArr
 * @return array
 * @throws Exception
 */
function sendMail($serv ,$sendArr)
{
    $recipients = $sendArr['to'];
    $replyTo = $sendArr['cc'];
    $title = $sendArr['subject'];
    $content = $sendArr['body'];
    $sendArr['attach'] = isset($sendArr['attach']) ? $sendArr['attach'] : '';
    $mailQueModel = new MailQueueModel();
    $mailQueModel->db->close();
    DbModel::$dalDriverInstances = [];
    $mailQueModel->connect();
    $settingModel = new SettingModel();
    $settings = $settingModel->getSettingByModule('mail');
    $config = [];
    if (empty($settings)) {
        $serv->finish("send mail -> failed, mail settings empty");
        return [false, 'fetch mail setting error'];
    }
    foreach ($settings as $s) {
        $config[$s['_key']] = $settingModel->formatValue($s);
    }
    unset($settings);

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->IsSMTP();
        $mail->CharSet = 'UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码
        $mail->SMTPAuth = true; //开启认证
        $mail->Port = (int)$sendArr['port'];
        $mail->SMTPDebug = 0;
        $mail->Host = $sendArr['host'];
        $mail->Username = $sendArr['user'];
        $mail->Password = $sendArr['password'];
        $mail->Timeout = isset($sendArr['timeout']) ? $sendArr['timeout'] : 20;
        $mail->From = trimStr($sendArr['from']);
        $mail->FromName = $sendArr['from_name'];
        if (isset($config['is_exchange_server']) && $config['is_exchange_server'] == '1') {
            $mail->setFrom($mail->From, $mail->FromName);
        }
        // 保留原代码，兼容已有的配置
        if (in_array($mail->Port, [465, 994, 995, 993])) {
            $mail->SMTPSecure = 'ssl';
        } else {
            $mail->SMTPSecure = 'tls';
        }
        // 是否启用ssl
        if (isset($config['is_ssl'])) {
            if ($config['is_ssl'] == '1') {
                $mail->SMTPSecure = 'ssl';
            } else {
                $mail->SMTPSecure = 'tls';
            }
        }
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        if (is_string($recipients)) {
            $recipients = [$recipients];
        }
        foreach ($recipients as $addr) {
            $addr = trimStr($addr);
            if (empty($addr)) {
                continue;
            }
            $mail->AddAddress($addr);
        }
        $mail->Subject = $title;
        $mail->Body = $content;
        if (is_string($replyTo)) {
            $replyTo = [$replyTo];
        }
        if (!empty($replyTo)) {
            if (is_array($replyTo)) {
                foreach ($replyTo as $r) {
                    $mail->addReplyTo(trimStr($r));
                }
            }
        }
        if (isset($sendArr['attach']) && !empty($sendArr['attach'])) {
            $mail->addAttachment($sendArr['attach']);
        }
        $mail->AltBody = "To view the message, please use an HTML compatible email viewer!"; //当邮件不支持html时备用显示，可以省略
        $mail->WordWrap = 80; // 设置每行字符串的长度
        $contentType = isset($sendArr['content_type']) ? $sendArr['content_type'] : 'html';
        $mail->IsHTML($contentType == 'html');
        $ret = $mail->Send();
        if (!$ret) {
            $msg = 'Mailer Error: ' . $mail->ErrorInfo;
            $mailQueModel->update(['status' => MailQueueModel::STATUS_ERROR, 'error' => $msg], ['seq' => $sendArr['seq']]);
        }
    } catch (\phpmailerException $e) {
        // print_r($e->getCode());
        //print_r($e->getTrace());
        $msg = "邮件发送失败：" . $e->errorMessage();
        echo $msg;
        $mailQueModel->update(['status' => MailQueueModel::STATUS_ERROR, 'error' => $msg], ['seq' => $sendArr['seq']]);
        $serv->finish("send mail -> failed: {$msg}");

    } catch (\Exception $e) {
        $msg = "邮件发送失败：" . $e->getMessage();
        echo $msg;
        $mailQueModel->update(['status' => MailQueueModel::STATUS_ERROR, 'error' => $msg], ['seq' => $sendArr['seq']]);
        $serv->finish("send mail -> failed: {$msg}");
    }
    $mailQueModel->update(['status' => MailQueueModel::STATUS_DONE, 'error' => ''], ['seq' => $sendArr['seq']]);
    //返回任务执行的结果
    $serv->finish("send mail -> OK");
}

/**
 * @param $serv
 * @param $sendArr
 * @return array
 * @throws \Doctrine\DBAL\DBALException
 * @throws \Exception
 */
function webhookPost($serv ,$sendArr)
{
    $pushArr = [];
    $pushArr['project_id'] = (int)$sendArr['project_id'];
    $pushArr['webhook_id'] = (int)$sendArr['webhook_id'];
    $pushArr['event_name'] = $sendArr['event_name'];
    $pushArr['url'] = $sendArr['url'];
    $pushArr['data'] = $sendArr['data'];;
    $pushArr['status'] = WebHookLogModel::STATUS_READY;
    $pushArr['time'] = time();
    $pushArr['user_id'] =  (int)$sendArr['user_id'];
    $pushArr['err_msg'] = '';
    // 0准备;1执行成功;2队列中;3出队列后执行失败
    $webhooklogModel = new WebHookLogModel();
    $webhooklogModel->db->close();
    DbModel::$dalDriverInstances = [];
    $webhooklogModel->connect();
    list($logRet, $logId) = $webhooklogModel->insert($pushArr);
    if ($logRet) {
        $pushArr['log_id'] = (int)$logId;
    } else {
        $pushArr['log_id'] = 0;
    }
    $ch = curl_init($pushArr['url']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,  $pushArr['data'] );
    curl_setopt($ch, CURLOPT_TIMEOUT,  20);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body = curl_exec($ch);
    //echo $body;
    $getinfo = curl_getinfo($ch);
    if ($getinfo['http_code'] == 200) {
        $webhooklogModel->updateById($logId, ['err_msg' => '', 'status' => WebHookLogModel::STATUS_SUCCESS]);
    }else{
        $webhooklogModel->updateById($logId, ['err_msg' => 'Response status code:'.$statusCode." \r\n".$body, 'status' => WebHookLogModel::STATUS_FAILED]);
    }
    //返回任务执行的结果
    $serv->finish("send mail -> OK");
}