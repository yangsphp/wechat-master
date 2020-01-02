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
        return self::$db->select("count(*) as num")->from(self::$_pre . "chat_friend")->where("status = 0 and is_deleted = 0 and to_user_id = {$uid}")->row();
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
            ->from(self::$_pre . "chat_msg as m")
            ->leftJoin(self::$_pre . "customer as c", "c.id=m.user_id")
            ->where("m.chat_id='{$chat_id}' and LOCATE({$uid}, deleted)=0")
            ->orderByASC(array("m.id"))
            ->query();
        foreach ($message_list as $i => $j) {
            if ($j['user_id'] == $uid) {
                $message_list[$i]["is_me"] = 1;
            } else {
                if ($j['is_read'] == 0) {
                    //设置消息已读
                    self::$db->update(self::$_pre . "chat_msg")->cols(array('is_read' => 1))->where('id=' . $j['id'])->query();
                }
                $message_list[$i]["is_me"] = 0;
            }
        }
        return $message_list;
    }

    /**
     * 获取当前用户聊天列表与第一个聊天的聊天历史
     * @param $uid
     * @return array
     */
    public static function getUserChatListById($uid)
    {
        //获取用户交谈的数据
        $histroy = self::$db->select("c.*, from.truename as from_truename, from.head_img as from_head_img, to.truename as to_truename, to.head_img as to_head_img")
            ->from(self::$_pre . "chat as c")
            ->leftJoin(self::$_pre . "customer as to", "to.id=c.to_user_id")
            ->leftJoin(self::$_pre . "customer as from", "from.id=c.from_user_id")
            ->where("(c.from_user_id = $uid or c.to_user_id = $uid) and c.is_delete = 0")
            ->orderByDESC(array("update_entered"))
            ->query();

        // 通知当前客户端初始化
        $message_list = array();
        foreach ($histroy as $k => $v) {
            $chat_id = self::get_chat_id($v['from_user_id'], $v['to_user_id']);
            //未读消息数量
            $histroy[$k]['number'] = 0;

            //获取交谈用户信息
            if ($v['from_user_id'] != $uid) {
                $histroy[$k]['uid'] = $v['from_user_id'];
                $histroy[$k]['truename'] = $v['from_truename'];
                $histroy[$k]['head_img'] = $v['from_head_img'];
            } elseif ($v['to_user_id'] != $uid) {
                $histroy[$k]['uid'] = $v['to_user_id'];
                $histroy[$k]['truename'] = $v['to_truename'];
                $histroy[$k]['head_img'] = $v['to_head_img'];
            }
            $time = date("y/m/d", strtotime($v['update_entered']));
            $histroy[$k]["time"] = $time;

            //删除无用数据
            unset($histroy[$k]['to_truename']);
            unset($histroy[$k]['to_head_img']);
            unset($histroy[$k]['from_truename']);
            unset($histroy[$k]['from_head_img']);

            if ($k == 0) {
                //获取第一个的聊天内容
                $message_list = self::getMessageListByChatId($uid, $chat_id);
                continue;
            }
            //计算未读信息
            $no_read = self::$db->select("count(id) as num")->from(self::$_pre . "chat_msg")->where("chat_id='$chat_id' and user_id != $uid and is_read = 0")->row();
            if ($no_read['num']) {
                $histroy[$k]['number'] = $no_read['num'];
            }
        }
        return array('history' => $histroy, 'message_list' => $message_list);
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
                $data = self::getUserChatListById($uid);
                //获取添加好友通知数量
                $add_friend_num = self::getSendFriendNum($uid);
                $response = array(
                    "type" => "init",
                    "user_list" => $data['history'],
                    "message_list" => $data['message_list'],
                    "friend_num" => $add_friend_num['num']
                );
                Gateway::sendToClient($client_id, json_encode($response));
                break;
            case 'chat_msg':
                //聊天
                $uid = $_SESSION['uid'];
                $time = date("Y-m-d H:i:s");
                switch ($message_data["sign"]) {
                    case 'friend':
                        $chat_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                        // 判断是否在聊天列表中
                        $res = self::$db->select("count(*) as num")->from(self::$_pre . "chat")->where("chat_id = '{$chat_id}'")->row();
                        if ($res['num'] == 0) {
                            $date = date("Y-m-d H:i:s", time());
                            self::$db->insert(self::$_pre . "chat")->cols(array(
                                'chat_id' => $chat_id,
                                'from_user_id' => $uid,
                                'to_user_id' => $message_data["to_user_id"],
                                'date_entered' => $date,
                                'update_entered' => $date,
                                'last_msg' => "",
                            ))->query();
                        }
                        // 插入聊天数据，并发送到客户端
                        $data = array(
                            'chat_id' => $chat_id,
                            'msg' => trim($message_data['msg']),
                            'user_id' => $uid,
                            'recv_user_id' => $message_data["to_user_id"],
                            'date_entered' => $time
                        );
                        $id = self::insertMessage($data);
                        $response = array(
                            "type" => "chat_msg",
                            "head_img" => $_SESSION['head_img'],
                            "msg" => $message_data['msg'],
                            "send_time" => date("H:i", strtotime($time)),
                            "uid" => $uid,
                            "to_user_id" => $message_data["to_user_id"]
                        );
                        if ($id) {
                            $response['status'] = 1;
                            Gateway::sendToUid($message_data["to_user_id"], json_encode($response));
                            //修改聊天时间和最后聊天记录
                            self::$db->update(self::$_pre . "chat")
                                ->cols(array('update_entered' => $time, "last_msg" => $message_data['msg'], 'is_delete' => 0))
                                ->where("chat_id='$chat_id'")
                                ->query();
                        } else {
                            //发送信息失败,告诉发送者
                            $response['status'] = 0;
                        }
                        Gateway::sendToUid($uid, json_encode($response));
                        break;
                    case 'group':

                        break;
                }
                break;
            case 'chat_msg_list':
                //获取聊天记录
                $uid = $_SESSION['uid'];
                $chat_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                switch ($message_data["sign"]) {
                    case 'friend':
                        //获取聊天历史
                        $message_list = self::getMessageListByChatId($uid, $chat_id);
                        $response = array(
                            "type" => "chat_msg_list",
                            "message_list" => $message_list
                        );
                        Gateway::sendToUid($uid, json_encode($response));
                        break;
                }
                break;
            case 'send_file_msg':
                $uid = $_SESSION['uid'];
                $time = date("Y-m-d H:i:s");
                switch ($message_data["sign"]) {
                    case 'friend':
                        $chat_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                        // 插入聊天数据，并发送到客户端
                        $data = array(
                            'chat_id' => $chat_id,
                            'msg' => trim($message_data['msg']),
                            'user_id' => $uid,
                            'recv_user_id' => $message_data["to_user_id"],
                            'date_entered' => $time
                        );
                        $id = self::insertMessage($data);
                        
                        if ($id) {
                            $response = array(
                                "type" => "chat_msg",
                                "head_img" => $_SESSION['head_img'],
                                "msg" => $message_data['msg'],
                                "send_time" => date("H:i", strtotime($time)),
                                "uid" => $uid,
                                "to_user_id" => $message_data["to_user_id"]
                            );
                            Gateway::sendToUid($message_data["to_user_id"], json_encode($response));
                            Gateway::sendToUid($uid, json_encode($response));
                            //修改聊天时间和最后聊天记录
                            if (in_array($message_data['file_type'], array('png', 'jpg', 'jpeg', 'gif'))) {
                                $msg = "[图片]";
                            }else{
                                $msg = "[文件]";
                            }
                            self::$db->update(self::$_pre . "chat")
                                ->cols(array('update_entered' => $time, "last_msg" => $msg, 'is_delete' => 0))
                                ->where("chat_id='$chat_id'")
                                ->query();
                        }else{
                            $response = array(
                                "type" => "notify",
                                "status" => 0,
                                "msg" => '发送信息失败'
                            );
                            Gateway::sendToUid($uid, json_encode($response));
                        }
                        break;
                    
                    default:
                        # code...
                        break;
                }
                break;
            case 'delete_chat':
                //删除聊天
                $uid = $_SESSION['uid'];
                $chat_id = self::get_chat_id($uid, $message_data["to_user_id"]);
                $result = self::$db->update(self::$_pre . "chat")->cols(array("is_delete" => 1))->where("chat_id='$chat_id'")->query();
                $response["type"] = "delete_chat";
                if ($result) {
                    $response["status"] = 1;
                    $response["to_user_id"] = $message_data["to_user_id"];
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
                $msg = self::$db->select("deleted")->from(self::$_pre . "chat_msg")->where("id=" . $message_data["msg_id"])->row();
                if ($msg['deleted']) {
                    $deleted = $msg['deleted'] . "," . $uid;
                } else {
                    $deleted = $uid;
                }
                $res = self::$db->update(self::$_pre . "chat_msg")->cols(array('deleted' => $deleted))->where("id=" . $message_data["msg_id"])->query();
                $response["type"] = "delete_chat_msg";
                $response["msg_id"] = $message_data["msg_id"];
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
                $msg = self::getMessageById($message_data["msg_id"]);
                $collection = self::$db->select("count(*) as num")->from(self::$_pre . "chat_collection")->where("user_id = $uid and from_user_id = {$msg["user_id"]} and msg = \"" . $msg['msg'] . "\"")->row();
                $response["type"] = "collection_chat_msg";
                if ($collection['num'] > 0) {
                    $response["status"] = 1;
                    $response['msg'] = "已收藏";
                } else {
                    $data = array(
                        "user_id" => $uid,
                        "from_user_id" => $msg["user_id"],
                        "msg" => $msg['msg'],
                        "msg_id" => $msg['id'],
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
                $msg = self::getMessageById($message_data["msg_id"]);
                $to_user_id_array = explode(",", $message_data['to_user_id']);
                $time = date("Y-m-d H:i:s", time());
                $response['type'] = $message_data['type'];
                $response['uid'] = $uid;
                $response['to_user_id'] = $message_data['to_user_id'];
                $response['msg'] = $msg['msg'];
                $response['head_img'] = $head_img;
                $response['send_time'] = date("H:i", strtotime($time));
                foreach ($to_user_id_array as $id) {
                    $chat_id = self::get_chat_id($uid, $id);
                    // 插入聊天数据，并发送到客户端
                    $data = array(
                        'chat_id' => $chat_id,
                        'msg' => $msg['msg'],
                        'user_id' => $uid,
                        'recv_user_id' => $id,
                        'date_entered' => $time
                    );
                    $msg_id = self::insertMessage($data);
                    if ($msg_id) {
                        $response['msg_id'] = $msg_id;
                        Gateway::sendToUid($id, json_encode($response));
                        Gateway::sendToUid($uid, json_encode($response));
                    }
                }
                break;
            case 'collection_list':
                //我的收藏列表
                $uid = $_SESSION['uid'];
                $collection = self::$db->select("c.*, t.truename")->from(self::$_pre . "chat_collection as c")->leftJoin(self::$_pre . "customer as t", "t.id = c.from_user_id")->where("user_id = $uid")->orderByDesc(array("c.id"))->query();
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
        }
    }

    /**
     * 获取消息
     * @param $msg_id 消息id
     * @return mixed  返回值
     */
    public static function getMessageById($msg_id)
    {
        return self::$db->select("*")->from(self::$_pre . "chat_msg")->where("id={$msg_id}")->row();
    }

    /**
     * 插入聊天数据
     * @param $data 插入字段数组
     * @return mixed
     */
    public static function insertMessage($data)
    {
        return self::$db->insert(self::$_pre . "chat_msg")->cols($data)->query();
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
