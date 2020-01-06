<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */

//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

require_once __DIR__ . "/mysql/src/Connection.php";

define("ROOT", dirname(dirname(dirname(dirname(__FILE__)))));

/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{
    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;
    public static $_pre = "car_";
    public static $_fileType = array('png', 'jpg', 'jpeg', 'gif');
    //消息类型：0：文本，1：图片，2：文件
    public static $_msgType = array(0, 1, 2);
    public static $_imgUploadPath = "/upload/chat/images";

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        self::$db = new \Workerman\MySQL\Connection('localhost', '3306', 'root', 'root', 'car');
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {

    }

    /**
     * 设置聊天id
     * @param $f
     * @param $t
     * @return string
     */
    public static function get_chat_id($f, $t)
    {
        return md5(strcmp($f, $t) > 0 ? $f . '|' . $t : $t . '|' . $f);
    }

    /**
     * 获取中文首字母
     * @param $s
     * @return bool|string
     */
    public static function getFirstChar($s)
    {
        $s0 = mb_substr($s, 0, 1, 'utf-8');//获取名字的姓
        $s = iconv('UTF-8', 'GBK', $s0);//将UTF-8转换成GB2312编码
        if (ord($s0) > 128) {//汉字开头，汉字没有以U、V开头的
            $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
            if ($asc >= -20319 and $asc <= -20284) return "A";
            if ($asc >= -20283 and $asc <= -19776) return "B";
            if ($asc >= -19775 and $asc <= -19219) return "C";
            if ($asc >= -19218 and $asc <= -18711) return "D";
            if ($asc >= -18710 and $asc <= -18527) return "E";
            if ($asc >= -18526 and $asc <= -18240) return "F";
            if ($asc >= -18239 and $asc <= -17760) return "G";
            if ($asc >= -17759 and $asc <= -17248) return "H";
            if ($asc >= -17247 and $asc <= -17418) return "I";
            if ($asc >= -17417 and $asc <= -16475) return "J";
            if ($asc >= -16474 and $asc <= -16213) return "K";
            if ($asc >= -16212 and $asc <= -15641) return "L";
            if ($asc >= -15640 and $asc <= -15166) return "M";
            if ($asc >= -15165 and $asc <= -14923) return "N";
            if ($asc >= -14922 and $asc <= -14915) return "O";
            if ($asc >= -14914 and $asc <= -14631) return "P";
            if ($asc >= -14630 and $asc <= -14150) return "Q";
            if ($asc >= -14149 and $asc <= -14091) return "R";
            if ($asc >= -14090 and $asc <= -13319) return "S";
            if ($asc >= -13318 and $asc <= -12839) return "T";
            if ($asc >= -12838 and $asc <= -12557) return "W";
            if ($asc >= -12556 and $asc <= -11848) return "X";
            if ($asc >= -11847 and $asc <= -11056) return "Y";
            if ($asc >= -11055 and $asc <= -10247) return "Z";
        } elseif (ord($s) >= 48 and ord($s) <= 57) {//数字开头
            switch (iconv_substr($s, 0, 1, 'utf-8')) {
                case 1:
                    return "Y";
                case 2:
                    return "E";
                case 3:
                    return "S";
                case 4:
                    return "S";
                case 5:
                    return "W";
                case 6:
                    return "L";
                case 7:
                    return "Q";
                case 8:
                    return "B";
                case 9:
                    return "J";
                case 0:
                    return "L";
            }
        } else if (ord($s) >= 65 and ord($s) <= 90) {//大写英文开头
            return substr($s, 0, 1);
        } else if (ord($s) >= 97 and ord($s) <= 122) {//小写英文开头
            return strtoupper(substr($s, 0, 1));
        } else {
            return iconv_substr($s0, 0, 1, 'utf-8');//中英混合的词语提取首个字符即可
        }
    }

    /**
     * 获取当前用户的朋友列表
     * @param $uid
     * @return array
     */
    public static function getFriendListByUid($uid)
    {
        $list = self::$db->select("user_id, to_user_id")->from(self::$_pre . "chat_friend")->where("(user_id=$uid or to_user_id=$uid) and status = 1 and is_deleted = 0")->query();
        $friend_id = array();
        foreach ($list as $k => $v) {
            if ($v['user_id'] == $uid) {
                $friend_id[] = $v['to_user_id'];
            } elseif ($v['to_user_id'] == $uid) {
                $friend_id[] = $v['user_id'];
            }
        }
        //获取好友信息
        $friend_id_str = implode(",", $friend_id);
        $customer = self::$db->select("*")->from(self::$_pre . "customer")->where("id in($friend_id_str) and is_deleted = 0")->query();
        $friend_list = array();
        foreach ($customer as $k => $v) {
            $c = self::getFirstChar($v['truename']);
            $friend_list[$c][] = $v;
        }
        //根据键排序
        ksort($friend_list);
        return $friend_list;
    }

    /**
     * 获取当前uid的未处理添加朋友请求数量
     * @param $uid
     * @return mixed
     */
    public static function getSendFriendNum($uid)
    {
        return self::$db->select("count(*) as num")
            ->from(self::$_pre . "chat_friend")
            ->where("status = 0 and is_deleted = 0 and to_user_id = {$uid}")
            ->row();
    }

    /**
     * 获取聊天信息列表
     * @param $uid
     * @param $chat_id
     * @return mixed
     */
    public static function getMessageListByChatId($uid, $chat_id)
    {
        $message_list = self::$db->select("m.*, c.truename, c.head_img")
            ->from(self::$_pre . "chat_msg_receive as r")
            ->leftJoin(self::$_pre . "chat_msg as m", "m.id = r.msg_id")
            ->leftJoin(self::$_pre . "customer as c", "c.id = m.send_user_id")
            ->where("m.chat_id = '$chat_id' and r.is_deleted = 0 and r.to_user_id = $uid")
            ->query();
        foreach ($message_list as $k => $v) {
            if ($v['send_user_id'] == $uid) {
                $message_list[$k]["is_me"] = 1;
            } else {
                $message_list[$k]["is_me"] = 0;
            }
        }
        return $message_list;
    }

    /**
     * 获取群成员信息
     * @param $group_id
     * @return mixed
     */
    public static function getGroupUserInfo($group_id)
    {
        $group_user = self::$db->select("c.*, u.user_id")
            ->from(self::$_pre . "chat_group_user as u")
            ->leftJoin(self::$_pre . "customer as c", "c.id = u.user_id")
            ->where("u.group_id = {$group_id}")
            ->query();
        return $group_user;
    }

    /**
     * 获取当前用户聊天列表
     * @param $uid
     * @return array
     */
    public static function getUserChatListById($uid)
    {
        $chat = self::$db->select("*")->from(self::$_pre . "chat as c")
            ->leftJoin(self::$_pre . "chat_last_msg as l", "c.chat_id = l.chat_id")
            ->where("c.user_id = $uid and c.is_deleted = 0")
            ->orderByASC(array("c.is_top"))
            ->query();
        $chat_list = array();
        if (count($chat) > 0) {
            $now_date = date("Y-m-d");
            foreach ($chat as $k => $v) {
                if ($v['update_entered']) {
                    $strtotime = strtotime($v['update_entered']);
                    if ($now_date == date("Y-m-d", $strtotime)) {
                        $time = date("H:i", $strtotime);
                    }else{
                        $time = date("y/m/d", $strtotime);
                    }
                } else {
                    $time = "";
                }
                //计算未读消息数量
                $no_read_num = self::getNoReadMsgNumber($uid, $v['chat_id']);
                $chat_list[$k] = array(
                    "chat_id" => $v['chat_id'],
                    "uid" => $v['anthor_id'],
                    "type" => $v['type'],
                    "last_msg" => $v['last_msg'],
                    "time" => $time
                );
                if ($v['type'] == 0) {
                    //一对一聊天
                    $user_info = self::getUserInfoById($v['anthor_id']);
                    $chat_list[$k]['group_count'] = 0;
                    $chat_list[$k]['no_read'] = $no_read_num;
                    $chat_list[$k]['truename'] = $user_info['truename'];
                    $chat_list[$k]['head_img'] = $user_info['head_img'];
                } else {
                    //群聊
                    //获取群成员信息
                    $group_user = self::getGroupUserInfo($v['anthor_id']);
                    //获取群信息
                    $group_info = self::getGroupInfo($v['anthor_id']);
                    $truename = "";
                    $head_img = array();
                    $count = 0;
                    foreach ($group_user as $key => $value) {
                        $truename .= $value['truename'] . "、";
                        if ($key < 9) {
                            $head_img[] = $value['head_img'];
                        }
                        $count++;
                    }
                    if ($group_info['group_name']) {
                        $truename = $group_info['group_name'];
                    }
                    $chat_list[$k]['group_count'] = count($group_user);
                    $chat_list[$k]['count'] = $count > 4 ? 3 : 2;
                    $chat_list[$k]['no_read'] = $no_read_num;
                    $chat_list[$k]['truename'] = trim($truename, "、");
                    $chat_list[$k]['head_img'] = $head_img;
                }
            }
        }
        return $chat_list;
    }

    /**
     * 计算未读消息
     * @param $uid
     * @param $chat_id
     * @return mixed
     */
    public static function getNoReadMsgNumber($uid, $chat_id)
    {
        $res = self::$db->select("count(*) as num")
            ->from(self::$_pre . "chat_msg_receive")
            ->where("to_user_id = $uid and is_read = 0 and is_deleted = 0 and chat_id = '$chat_id'")
            ->row();
        return $res['num'];
    }

    /**
     * 设置消息为已读
     * @param $uid
     * @param $chat_id
     * @return bool
     */
    public static function setMsgIsRead($uid, $chat_id)
    {
        $res = self::$db->update(self::$_pre . "chat_msg_receive")
            ->cols(array(
                "is_read" => 1
            ))->where("chat_id = '$chat_id' and to_user_id = $uid and is_deleted = 0 and is_read = 0")->query();
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * 判断用户是否在聊天列表中
     * @param $uid
     * @param $anthor_id
     * @return bool
     */
    public static function judgeUserInChatList($uid, $chat_id)
    {
        $res = self::$db->select("count(*) as num")
            ->from(self::$_pre . "chat")
            ->where("chat_id = '{$chat_id}' and user_id = $uid and is_deleted = 0")
            ->row();
        if ($res['num'] > 0) {
            return true;
        }
        return false;
    }

    /**
     * 修改聊天时间和最后聊天记录
     * @param $chat_id
     * @param $msg
     * @param $time
     * @return mixed
     */
    public static function updateChatLastTimeAndMsg($chat_id, $msg, $time)
    {
        return self::$db->update(self::$_pre . "chat_last_msg")
            ->cols(array('update_entered' => $time, "last_msg" => $msg))
            ->where("chat_id='{$chat_id}'")
            ->query();
    }

    /**
     * 删除聊天信息
     * @param $uid
     * @param $msg_id
     * @return mixed
     */
    public static function deleteChatMsg($uid, $msg_id)
    {
        return self::$db->update(self::$_pre . "chat_msg_receive")
            ->cols(array("is_deleted" => 1))
            ->where("msg_id = $msg_id and to_user_id = $uid")
            ->query();
    }

    /**
     * 删除聊天
     * @param $uid
     * @param $chat_id
     * @return mixed
     */
    public static function deleteChat($uid, $chat_id)
    {
        return self::$db->update(self::$_pre . "chat")
            ->cols(array("is_deleted" => 1))
            ->where("chat_id='$chat_id' and user_id = $uid")
            ->query();
    }

    /**
     * 插入聊天
     * @param int $group_id
     * @return mixed
     */
    public static function insertChat($uid, $chat_id, $anthor_id, $group_id = 0)
    {
        $date = date("Y-m-d H:i:s", time());
        $data = array(
            "chat_id" => $chat_id,
            "user_id" => $uid,
            "date_entered" => $date
        );
        if ($group_id) {
            $data['anthor_id'] = $group_id;
            $data['type'] = 1;
        } else {
            $data['anthor_id'] = $anthor_id;
        }
        return self::$db->insert(self::$_pre . "chat")
            ->cols($data)
            ->query();
    }

    /**
     * 插入最后聊天信息
     * @param $chat_id
     * @return bool
     */
    public static function insertLastMsg($chat_id)
    {
        //判断是否已经存在
        $res = self::$db->select("count(*) as num")
            ->from(self::$_pre . "chat_last_msg")
            ->where("chat_id='{$chat_id}'")
            ->row();
        if ($res['num'] > 0) {
            return true;
        }
        return self::$db->insert(self::$_pre . "chat_last_msg")
            ->cols(array(
                "chat_id" => $chat_id,
                "last_msg" => ""
            ))
            ->query();
    }

    /**
     * 根据用户id获取用户信息
     * @param $uid
     * @return mixed
     */
    public static function getUserInfoById($uid)
    {
        return self::$db->select("*")
            ->from(self::$_pre . "customer")
            ->where("id = $uid")
            ->row();
    }

    /**
     * 获取群聊信息
     * @param $group_id
     * @return mixed
     */
    public static function getGroupInfo($group_id)
    {
        return self::$db->select("*")
            ->from(self::$_pre . "chat_group")
            ->where("id = $group_id")
            ->row();
    }

    /**
     * 判断是否已经收藏
     * @param $uid
     * @param $from
     * @param $msg
     * @return mixed
     */
    public static function judgeMsgInCollection($uid, $msg_id)
    {
        $res = self::$db->select("count(*) as num")
            ->from(self::$_pre . "chat_collection")
            ->where("user_id = $uid and msg_id = $msg_id")
            ->row();
        return $res['num'];
    }

    /**
     * 判断消息类型
     * @param $msg_type
     * @return int
     */
    public static function judgeMsgType($msg_type)
    {
        if (isset($msg_type) && $msg_type)
        {
            if (in_array($msg_type, self::$_fileType)) {
                return 1;
            } else {
                return 2;
            }
        }
        return 0;
    }

    /**
     * 上传图片到服务器
     * @param $base64_image_content
     * @param $path
     * @return bool|string
     */
    public static function base64_image_content($base64_image_content,$path){
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
            $type = $result[2];
            $new_file = $path."/".date('Ymd',time())."/";
            $basePutUrl = $new_file;
            $file_name = "img_".time().rand(1000, 9999).".{$type}";
            $local_file_url = ROOT.$basePutUrl.$file_name;
            if(!file_exists($basePutUrl)){
                //检查是否有该文件夹，如果没有就创建，并给予最高权限
                mkdir($basePutUrl, 0700, true);
            }
            if (file_put_contents($local_file_url, base64_decode(str_replace($result[1], '', $base64_image_content)))){
                return $new_file.$file_name;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";
        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if (!$message_data) {
            echo "数据格式不正确" . "\n";
            return;
        }

        switch ($message_data["type"]) {
            case 'login':
                $uid = $message_data['client_id'];
                //客户端初始化登录
                $_SESSION = array(
                    "uid" => $uid,
                    "name" => $message_data['client_name'],
                    "truename" => $message_data['client_truename'],
                    "head_img" => $message_data['client_head_img'],
                );
                //与uid绑定
                Gateway::bindUid($client_id, $uid);
                //获取当前用户聊天列表
                $chat_list = self::getUserChatListById($uid);
                //获取添加好友通知数量
                $add_friend_num = self::getSendFriendNum($uid);

                $message_list = array();
                if ($chat_list) {
                    //获取第一个聊天列表的交谈历史记录
                    $message_list = self::getMessageListByChatId($uid, $chat_list[0]['chat_id']);
                    //设置第一个聊天列表的交谈历史记录为已读
                    self::setMsgIsRead($uid, $chat_list[0]['chat_id']);
                }

                $response = array(
                    "type" => "init",
                    "user_list" => $chat_list,
                    "message_list" => $message_list,
                    "friend_num" => $add_friend_num['num']
                );
                Gateway::sendToClient($client_id, json_encode($response));
                break;
            case 'chat_msg':
                //聊天
                $uid = $_SESSION['uid'];
                $time = date("Y-m-d H:i:s");
                $chat_id = $message_data['chat_id'];
                $anthor_id = $message_data['anthor_id'];
                $sign = $message_data["sign"];
                $source_msg = trim($message_data['msg']);
                $msg_type = self::judgeMsgType($message_data['file_type']);
                if ($msg_type == 0)
                {
                    $left_msg =  trim($message_data['msg']);
                }elseif ($msg_type == 1)
                {
                    $left_msg = "[图片]";
                    //上传图片
                    preg_match("/(data:[^']+)'/i",$source_msg,$match);
                    $upload_url = self::base64_image_content($match[1], self::$_imgUploadPath);
                    $source_msg = preg_replace("/(data:[^\']+)/i",$upload_url,$source_msg);
                    var_dump($source_msg);
                }elseif ($msg_type == 2)
                {
                    $left_msg = "[文件]";
                }
                //插入的聊天数据
                $msg_data = array(
                    'chat_id' => $chat_id,
                    'msg_type' => $msg_type,
                    'msg' => $source_msg,
                    'send_user_id' => $uid,
                    'date_entered' => $time
                );
                switch ($sign) {
                    case 0:
                        // 判断是否在聊天列表中
                        if (!self::judgeUserInChatList($anthor_id, $chat_id)) {
                            //接收消息用户不在聊天列表中，添加聊天列表
                            self::insertChat($anthor_id, $chat_id, $uid, 0);
                        }
                        // 插入聊天数据，并发送到客户端
                        $user = array($uid, $anthor_id);
                        //添加聊天信息
                        $id = self::insertMessage($user, $msg_data);
                        if ($id) {
                            $response = array(
                                "type" => "chat_msg",
                                "head_img" => $_SESSION['head_img'],
                                "chat_id" => $message_data['chat_id'],
                                "msg" => $message_data['msg'],
                                "left_msg" => $left_msg,
                                'msg_type' => $msg_type,
                                "msg_id" => $id,
                                "send_time" => date("H:i", strtotime($time)),
                                "uid" => $uid,
                                "to_user_id" => $anthor_id
                            );
                            $response['status'] = 1;
                            Gateway::sendToUid($anthor_id, json_encode($response));
                            Gateway::sendToUid($uid, json_encode($response));
                            //修改聊天时间和最后聊天记录
                            self::updateChatLastTimeAndMsg($chat_id, $left_msg, $time);
                        } else {
                            //发送信息失败,告诉发送者
                            $response['status'] = 0;
                            $response['msg'] = "发送消息失败";
                            Gateway::sendToUid($uid, json_encode($response));
                        }
                        break;
                    case 1:
                        //群聊
                        //获取所有的群成员信息
                        $group_user_info = self::getGroupUserInfo($anthor_id);
                        $user = array();
                        foreach ($group_user_info as $k => $v) {
                            // 判断是否在聊天列表中
                            $user_id = $v['user_id'];
                            if (!self::judgeUserInChatList($user_id, $chat_id)) {
                                //接收消息用户不在聊天列表中，添加聊天列表
                                self::insertChat($user_id, $chat_id, $uid, $anthor_id);
                            }
                            $user[] = $user_id;
                        }
                        $id = self::insertMessage($user, $msg_data);
                        if ($id) {
                            $response = array(
                                "type" => "chat_msg",
                                "head_img" => $_SESSION['head_img'],
                                "chat_id" => $message_data['chat_id'],
                                "msg" => $message_data['msg'],
                                'msg_type' => $msg_type,
                                "left_msg" => $left_msg,
                                "msg_id" => $id,
                                "send_time" => date("H:i", strtotime($time)),
                                "uid" => $uid
                            );
                            $response['status'] = 1;
                            //将消息推送给群成员
                            //Gateway::sendToUid($user, json_encode($response));
                            foreach ($group_user_info as $k => $v) {
                                Gateway::sendToUid($v['user_id'], json_encode($response));
                            }
                            //修改聊天时间和最后聊天记录
                            self::updateChatLastTimeAndMsg($chat_id, $left_msg, $time);
                        } else {
                            //发送信息失败,告诉发送者
                            $response['status'] = 0;
                            $response['msg'] = "发送消息失败";
                            Gateway::sendToUid($uid, json_encode($response));
                        }
                        break;
                }
                break;
            case 'chat_msg_list':
                //获取聊天记录
                $uid = $_SESSION['uid'];
                //获取聊天历史
                $message_list = self::getMessageListByChatId($uid, $message_data['chat_id']);
                //设置消息为已读
                self::setMsgIsRead($uid, $message_data['chat_id']);
                $response = array(
                    "type" => "chat_msg_list",
                    "message_list" => $message_list
                );
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'delete_chat':
                //删除聊天
                $uid = $_SESSION['uid'];
                $chat_id = $message_data["chat_id"];
                $result = self::deleteChat($uid, $chat_id);
                $response["type"] = "delete_chat";
                if ($result) {
                    $response["status"] = 1;
                    $response["chat_id"] = $chat_id;
                    $response['msg'] = "删除聊天成功";
                } else {
                    $response['status'] = 0;
                    $response['msg'] = "删除聊天失败";
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'delete_chat_msg':
                //删除聊天信息
                $uid = $_SESSION['uid'];
                $msg_id = $message_data['msg_id'];
                $res = self::deleteChatMsg($uid, $msg_id);
                $response["type"] = "delete_chat_msg";
                $response["msg_id"] = $msg_id;
                if ($res) {
                    $response["status"] = 1;
                    $response['msg'] = "删除聊天信息成功";
                } else {
                    $response['status'] = 0;
                    $response['msg'] = "删除聊天信息失败";
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'add_friend':
                //添加朋友
                $uid = $_SESSION['uid'];
                $response["type"] = "add_friend";
                if ($uid == $message_data["to_user_id"]) {
                    $response['status'] = 0;
                    $response['msg'] = "不能添加自己为朋友";
                } else {
                    //判断是否已经添加了
                    $friend_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                    $info = self::$db->select("*")->from(self::$_pre . "chat_friend")->where("friend_id='{$friend_id}' and is_deleted = 0")->query();
                    if (count($info) > 0) {
                        $response['status'] = 0;
                        $response['msg'] = "他已经是你的朋友了";
                    } else {
                        $data = array(
                            "user_id" => $uid,
                            "friend_id" => $friend_id,
                            "to_user_id" => $message_data["to_user_id"],
                            "date_entered" => date("Y-m-d H:i:s", time())
                        );
                        $res = self::$db->insert(self::$_pre . "chat_friend")->cols($data)->query();
                        if ($res) {
                            $response['status'] = 1;
                            $response['msg'] = "添加成功，等待通过";
                            //发送一条消息：你好，我是XX

                        } else {
                            $response['status'] = 0;
                            $response['msg'] = "添加失败";
                        }
                    }
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'add_friend_ok':
                $uid = $_SESSION['uid'];
                //通过添加好友请求
                $friend_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                $res = self::$db->update(self::$_pre . "chat_friend")->cols(array("status" => 1))->where("friend_id='{$friend_id}'")->query();

                $friend_list = self::getFriendListByUid($uid);
                $response["type"] = "add_friend_ok";
                if ($res) {
                    $response['status'] = 1;
                    $response['msg'] = "添加{$_SESSION['truename']}为好友验证通过";
                    $response['friend_list'] = $friend_list;
                } else {
                    $response['status'] = 0;
                    $response['msg'] = "用户已拒绝添加";
                }
                //发送给申请人
                Gateway::sendToUid($message_data["to_user_id"], json_encode($response));
                //发送给接受人
                Gateway::sendToUid($uid, json_encode(array(
                    "type" => "user_is_notify",
                    "friend_list" => $friend_list,
                    "friend_num" => self::getSendFriendNum($uid)
                )));
                break;
            case 'friend_list':
                $uid = $_SESSION['uid'];
                //获取当前用户的朋友列表
                $friend_list = self::getFriendListByUid($uid);
                $response["type"] = "friend_list";
                $response["friend_list"] = $friend_list;
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'delete_friend':
                //删除朋友
                $uid = $_SESSION['uid'];
                $truename = $_SESSION['truename'];
                $res = self::$db->update(self::$_pre . "chat_friend")->cols(
                    array("is_deleted" => 1)
                )->where("user_id=$uid and to_user_id={$message_data['friend_id']}")->orWhere("to_user_id=$uid and user_id={$message_data['friend_id']}")->query();
                $response["type"] = $message_data['type'];
                if ($res) {
                    $response["status"] = 1;
                    $response['msg'] = "删除朋友成功";
                    Gateway::sendToUid($message_data['friend_id'], json_encode(array(
                        "type" => "notify",
                        "msg" => "您已被{$truename}删除"
                    )));
                } else {
                    $response["status"] = 0;
                    $response['msg'] = "删除朋友失败";
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'collection_chat_msg':
                //收藏聊天信息
                $uid = $_SESSION['uid'];
                $msg_id = $message_data["msg_id"];
                //获取收藏的消息
                $msg_info = self::getMessageById($msg_id);
                $from_user_id = $message_data['from_user_id'];
                $type = $message_data['sign'];
                if ($type == 0) {
                    //朋友
                    $user_info = self::getUserInfoById($msg_info['send_user_id']);
                    $from = $user_info['truename'];
                } elseif ($type == 1) {
                    //群聊
                    $group_info = self::getGroupInfo($from_user_id);
                    $from = $group_info['group_name'];
                }
                $collection_num = self::judgeMsgInCollection($uid, $msg_id);
                $response["type"] = "collection_chat_msg";
                if ($collection_num > 0) {
                    $response["status"] = 1;
                    $response['msg'] = "已收藏";
                } else {
                    $data = array(
                        "user_id" => $uid,
                        "from" => $from,
                        "msg" => $msg_info['msg'],
                        "msg_id" => $msg_id,
                        "date_entered" => date("Y-m-d H:i:s", time())
                    );
                    $res = self::$db->insert(self::$_pre . "chat_collection")->cols($data)->query();
                    if ($res) {
                        $response["status"] = 1;
                        $response['msg'] = "收藏成功";
                    } else {
                        $response["status"] = 0;
                        $response['msg'] = "收藏失败";
                    }
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'send_msg_to_friends':
                //发送指定消息给其他朋友
                $uid = $_SESSION['uid'];
                $head_img = $_SESSION['head_img'];
                $msg_info = self::getMessageById($message_data["msg_id"]);
                $to_user_id_array = explode(",", $message_data['to_user_id']);
                $time = date("Y-m-d H:i:s", time());
                $response['type'] = $message_data['type'];
                foreach ($to_user_id_array as $id) {
                    $chat_id = self::get_chat_id($uid, $id);
                    //判断发送者是否已经在列表中
                    if (!self::judgeUserInChatList($uid, $chat_id)) {
                        //插入聊天列表
                        $res = self::insertChat($uid, $chat_id, $id);
                        if ($res) {
                            self::insertLastMsg($chat_id);
                        }
                    }
                    //判断接收者是否在聊天列表中
                    if (!self::judgeUserInChatList($id, $chat_id)) {
                        //插入聊天列表
                        $res = self::insertChat($id, $chat_id, $uid);
                        if ($res) {
                            self::insertLastMsg($chat_id);
                        }
                    }
                    // 插入聊天数据，并发送到客户端
                    $msg_data = array(
                        'chat_id' => $chat_id,
                        'msg' => trim($msg_info['msg']),
                        'send_user_id' => $uid,
                        'date_entered' => $time
                    );
                    $msg_id = self::insertMessage(array($uid, $id), $msg_data);
                    if ($msg_id) {
                        //修改聊天时间和最后聊天记录
                        self::updateChatLastTimeAndMsg($chat_id, $msg_info['msg'], $time);
                        Gateway::sendToUid($id, json_encode($response));
                    }
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'collection_list':
                //我的收藏列表
                $uid = $_SESSION['uid'];
                $collection = self::$db->select("c.*")
                    ->from(self::$_pre . "chat_collection as c")
                    ->where("user_id = $uid")
                    ->orderByDesc(array("c.id"))->query();
                foreach ($collection as $k => $v) {
                    $collection[$k]['date'] = date("y/m/d", strtotime($v['date_entered']));
                }
                $response["type"] = $message_data['type'];
                $response["collection_list"] = $collection;
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'delete_collection':
                $uid = $_SESSION['uid'];
                //删除收藏
                $res = self::$db->delete(self::$_pre . "chat_collection")->where("id = " . $message_data['collection_id'])->query();
                $response["type"] = $message_data['type'];
                if ($res) {
                    $response["status"] = 1;
                    $response["collection_id"] = $message_data['collection_id'];
                    $response['msg'] = "删除成功";
                } else {
                    $response["status"] = 0;
                    $response['msg'] = "删除失败";
                }
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'send_msg_to_user':
                //在通讯录中发送消息给朋友
                $uid = $_SESSION['uid'];
                $to_user_id = $message_data['to_user_id'];
                //获取聊天id
                $chat_id = self::get_chat_id($uid, $to_user_id);
                if (!self::judgeUserInChatList($uid, $chat_id)) {
                    //插入聊天列表
                    $res = self::insertChat($uid, $chat_id, $to_user_id);
                    if ($res) {
                        self::insertLastMsg($chat_id);
                    }
                }
                $chat_list = self::getUserChatListById($uid);
                $response = array(
                    "type" => $message_data['type'],
                    "user_list" => $chat_list,
                    "to_user_id" => $to_user_id,
                    "msg" => "创建聊天成功"
                );
                Gateway::sendToUid($uid, json_encode($response));
                break;
            case 'update_chat_list':
                //更新聊天列表
                $uid = $message_data['user_id'];
                $active_chat_id = $message_data['active_chat_id'];
                $chat_list = self::getUserChatListById($uid);
                //如果聊天列表不存在，则默认显示第一个
                if (!$active_chat_id) {
                    $active_chat_id = $chat_list[0]['chat_id'];
                }
                //获取第一个聊天列表的交谈历史记录
                $message_list = self::getMessageListByChatId($uid, $active_chat_id);
                //设置第一个聊天列表的交谈历史记录为已读
                self::setMsgIsRead($uid, $active_chat_id);
                $response = array(
                    "type" => "update_chat_list",
                    "user_list" => $chat_list,
                    "message_list" => $message_list,
                    "active_chat_id" => $active_chat_id
                );
                Gateway::sendToUid($uid, json_encode($response));
                break;
        }
    }

    /**
     * 获取消息
     * @param $msg_id 消息id
     * @return mixed  返回值
     */
    public static function getMessageById($msg_id)
    {
        return self::$db->select("*")->from(self::$_pre . "chat_msg")->where("id=$msg_id")->row();
    }

    /**
     * 插入聊天数据
     * @param $data 插入字段数组
     * @return mixed
     */
    public static function insertMessage($user, $data)
    {
        $uid = $_SESSION['uid'];
        $msg_id = self::$db->insert(self::$_pre . "chat_msg")->cols($data)->query();
        $chat_msg_receive = array(
            "chat_id" => $data['chat_id'],
            "msg_id" => $msg_id,
            "date_entered" => $data['date_entered']
        );
        foreach ($user as $k => $v) {
            $chat_msg_receive['to_user_id'] = $v;
            //如果是自己发送的消息，则设置为已读状态
            if ($uid == $v) {
                $chat_msg_receive['is_read'] = 1;
            } else {
                $chat_msg_receive['is_read'] = 0;
            }
            self::$db->insert(self::$_pre . "chat_msg_receive")->cols($chat_msg_receive)->query();
        }
        return $msg_id;
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
        Gateway::closeClient($client_id);
        // 从房间的客户端列表中删除
//       if(isset($_SESSION['room_id']))
//       {
//           $room_id = $_SESSION['room_id'];
//           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
//           Gateway::sendToGroup($room_id, json_encode($new_message));
//       }
    }
}
