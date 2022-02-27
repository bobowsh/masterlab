<?php

/**
 * Created by PhpStorm.
 * User: sven
 * Date: 2017/7/7 0007
 * Time: 下午 3:56
 */

namespace main\app\classes;

use main\app\model\issue\IssueModel;
use main\app\model\project\ProjectModel;
use main\app\model\user\UserModel;

/**
 * 全文搜索逻辑类
 * Class SearchLogic
 * @package main\app\classes
 */
class SearchLogic
{

    public static $mysqlVersion = 0;

    /**
     * 通过id从数据库查询数据
     * @param $issueIdArr
     * @return array
     * @throws \Exception
     */
    public static function getIssueByDb($issueIdArr)
    {
        if (empty($issueIdArr)) {
            return [];
        }
        $issueModel = new IssueModel();
        $issueIdStr = implode(',', $issueIdArr);
        $table = $issueModel->getTable();

        $sql = "SELECT * FROM {$table} WHERE id in({$issueIdStr}) ";
        //var_dump($sql);
        $rows = $issueModel->db->fetchAll($sql);
        return $rows;
    }

    /**
     * 直接从项目表中查询数据
     * @param int $keyword
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public static function getProjectByKeyword($keyword = 0, $page = 1, $pageSize = 50)
    {
        $start = $pageSize * ($page - 1);
        $limitSql = "   limit $start, " . $pageSize;

        $model = new ProjectModel();
        $table = $model->getTable();
        $field = "*";
        //if (self::$mysqlVersion < 5.70) {
            // 使用LOCATE模糊搜索
            $where = "WHERE   locate(:keyword,name) > 0  OR  locate(:keyword,`key`) > 0 ";
        //} else {
            // 使用全文索引
        //    $where =" WHERE MATCH (`name`) AGAINST (:keyword IN NATURAL LANGUAGE MODE) ";
        //}

        $params['keyword'] = $keyword;

        $sql = "SELECT {$field} FROM {$table} " . $where . $limitSql;
        //echo $keyword;
        //echo $sql;
        $projects = $model->db->fetchAll($sql, $params);
        return $projects;
    }

    /**
     * 获取项目搜索的总数
     * @param $keyword
     * @return int
     * @throws \Exception
     */
    public static function getProjectCountByKeyword($keyword)
    {
        $model = new ProjectModel();
        $table = $model->getTable();
        // var_export(self::$mysqlVersion);
        //if (self::$mysqlVersion < 5.70) {
            // 使用LOCATE模糊搜索
            $where = "WHERE locate(:keyword,name) > 0  OR  locate(:keyword,`key`) > 0 ";
        //} else {
            // 使用全文索引
        //    $where =" WHERE MATCH (`name`) AGAINST (:keyword IN NATURAL LANGUAGE MODE) ";
        //}
        $params['keyword'] = $keyword;

        $sqlCount = "SELECT count(*)  as cc  FROM {$table}  " . $where;
        // echo $sqlCount;
        $count = $model->getFieldBySql($sqlCount, $params);

        return (int)$count;
    }

    /**
     * Mysql5.7以上版本使用内置的全文索引插件Ngram获取记录
     * @param int $keyword
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public static function getIssueByKeywordWithNgram($keyword = 0, $page = 1, $pageSize = 50)
    {
        $start = $pageSize * ($page - 1);
        $limitSql = "   limit $start, " . $pageSize;
        
       // $userModel = UserModel::getInstance();
       // $user = $userModel->getByDisplayname($keyword);
       // $uid="unknown";
       // if (isset($user['uid'])) {
      //      $uid=$user['uid'];
      //  }
       
        $issueModel = new IssueModel();
        $table = $issueModel->getTable();
        //if (self::$mysqlVersion < 5.70) {
            // 使用LOCATE模糊搜索
           // $where = "WHERE locate(:keyword,`summary`) > 0  or locate(:keyword,description) > 0 or locate(:uid, assignee)>0";
           $where = "WHERE locate(:keyword,`summary`) > 0  or locate(:keyword,description) > 0";
        //} else {
            // 使用全文索引
        //    $where =" WHERE MATCH (`summary`) AGAINST (:keyword IN NATURAL LANGUAGE MODE) ";
        //}

        $params['keyword'] = $keyword;
      //  $params['uid'] = $uid;
        $sql = "SELECT * FROM {$table}  {$where} {$limitSql}";
        var_dump($sql);
        $rows = $issueModel->db->fetchAll($sql, $params);
        return $rows;
    }

    /**
     * Mysql5.7以上版本使用内置的全文索引插件Ngram获取总数
     * @param $keyword
     * @return int
     * @throws \Exception
     */
    public static function getIssueCountByKeywordWithNgram($keyword)
    {
        $model = new IssueModel();
        $table = $model->getTable();

        //if (self::$mysqlVersion < 5.70) {
            // 使用LOCATE模糊搜索
            $where = "WHERE locate(:keyword,`summary`) > 0  or locate(:keyword,description) > 0";
        //} else {
            // 使用全文索引
        //    $where =" WHERE MATCH (`summary`) AGAINST (:keyword IN NATURAL LANGUAGE MODE) ";
        //}
        $params['keyword'] = $keyword;

        $params['keyword'] = $keyword;
        $sqlCount = "SELECT count(*)  as cc  FROM {$table}  " . $where;
        //echo $sqlCount;
        $count = $model->getFieldBySql($sqlCount, $params);
        return (int)$count;
    }

    /**
     * 用户查询
     * @param int $keyword
     * @param int $page
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public static function getUserByKeyword($keyword = 0, $page = 1, $pageSize = 50)
    {
        $start = $pageSize * ($page - 1);
        $limitSql = "   limit $start, " . $pageSize;

        $model = new UserModel();
        $table = $model->getTable();
        $normalStatus = UserModel::STATUS_NORMAL;
        $field = "uid,username,display_name,email,create_time,update_time,avatar";
        $where = "WHERE status={$normalStatus} AND (locate( :email,email) > 0 || locate( :username,username) > 0  || locate( :display_name,display_name) > 0 )  ";
        $params['email'] = $keyword;
        $params['username'] = $keyword;
        $params['display_name'] = $keyword;

        $sql = "SELECT {$field} FROM {$table}  " . $where . $limitSql;
        $users = $model->db->fetchAll($sql, $params);
        foreach ($users as &$item) {
            $item = UserLogic::format($item);
        }
        return $users;
    }


    /**
     * 获取用户搜索的总数
     * @param $keyword
     * @return int
     * @throws \Exception
     */
    public static function getUserCountByKeyword($keyword)
    {
        $model = new UserModel();
        $table = $model->getTable();
        $normalStatus = UserModel::STATUS_NORMAL;
        $where = "WHERE status={$normalStatus} AND (locate( :email,email) > 0 || locate( :username,username) > 0  || locate( :display_name,display_name) > 0 )  ";
        $params['email'] = $keyword;
        $params['username'] = $keyword;
        $params['display_name'] = $keyword;
        $sqlCount = "SELECT count(*)  as cc  FROM {$table}  " . $where;
        $count = $model->getFieldBySql($sqlCount, $params);

        return (int)$count;
    }
}
