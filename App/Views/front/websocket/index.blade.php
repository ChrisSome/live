<!doctype html>
<html lang="zh-Hans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>微聊 - EASYSWOOLE DEMO</title>
    <link rel="stylesheet" href="https://cdn.staticfile.org/amazeui/2.7.2/css/amazeui.min.css">
    <link rel="stylesheet" href="https://cdn.staticfile.org/layer/2.3/skin/layer.css">
    <link rel="stylesheet" href="/css/main.css?v=120203">
    <script src="https://cdn.staticfile.org/vue/2.5.17-beta.0/vue.js"></script>
    <script src="https://cdn.staticfile.org/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdn.staticfile.org/layer/2.3/layer.js"></script>
</head>
<body>
<div id="chat">
    <template>
        <div class="online_window">
            <div class="me_info">
                <div class="me_item">
                    <div class="me_avatar">
                        <img :src="currentUser.avatar" alt="" :title="currentUser.fd">
                    </div>
                    <div class="me_status">
                        <div class="me_username">
                            <i class="am-icon am-icon-pencil" @click="changeName"></i> {{currentUser.username}}
                        </div>
                        <div class="me_income">{{currentUser.intro}}</div>
                    </div>
                    <div class="times-icon"><i class="am-icon am-icon-times"></i></div>
                </div>
            </div>
            <div class="online_list">
                <div class="online_list_header">车上乘客</div>
                <div class="online_item" v-for="user in roomUser">
                    <template v-if="user">
                        <div class="online_avatar">
                            <img :src="user.avatar" alt="">
                        </div>
                        <div class="online_status">
                            <div class="online_username">{{user.username}}</div>
                        </div>
                    </template>
                </div>
            </div>
            <div class="online_count">
                <h6>车上乘客 <span>{{currentCount}}</span> 位</h6>
            </div>
        </div>
        <div class="talk_window">
            <div class="windows_top">
                <div class="windows_top_left"><i class="am-icon am-icon-list online-list"></i> 欢迎乘坐特快列车</div>
            </div>
            <div class="windows_body" id="chat-window" v-scroll-bottom>
                <ul class="am-comments-list am-comments-list-flip">
                    <template v-for="chat in roomChat" >
                        <template v-if="chat.type === 'tips'">
                            <div class="chat-tips">
                                <span class="am-badge am-badge-primary am-radius">{{chat.content}}</span></div>
                        </template>
                        <template v-else>
                            <div v-if="chat.sendTime" class="chat-tips">
                                <span class="am-radius" style="color: #666666">{{chat.sendTime}}</span>
                            </div>
                            <article :chat="chat.sendUserId"  :cu-fd="currentUser.id" :class="chat.sendUserId == currentUser.id ? 'am-comment-flip' : 'am-comment' ">
                                <a href="#link-to-user-home">
                                    <img :src="chat.avatar" alt="" class="am-comment-avatar"
                                         width="48" height="48"/>
                                </a>
                                <div class="am-comment-main">
                                    <header class="am-comment-hd">
                                        <div class="am-comment-meta">
                                            <a href="#link-to-user" class="am-comment-author">{{chat.username}}</a>
                                        </div>
                                    </header>
                                    <div class="am-comment-bd">
                                        <div class="bd-content">
                                            <template v-if="chat.type === 'text'">
                                                {{chat.content}}
                                            </template>
                                            <template v-else-if="chat.type === 'image'">
                                                <img :src="chat.content" width="100%">
                                            </template>
                                            <template v-else>
                                                {{chat.content}}
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </template>
                </ul>
            </div>
            <div class="windows_input">
                <div class="am-btn-toolbar">
                    <div class="am-btn-group am-btn-group-xs">
                        <button type="button" class="am-btn" @click="picture"><i class="am-icon am-icon-picture-o"></i>
                        </button>
                        <input type="file" id="fileInput" style="display: none" accept="image/*">
                    </div>
                </div>
                <div class="input-box">
                    <label for="text-input" style="display: none"></label>
                    <textarea name="" id="text-input" cols="30" rows="10" title=""></textarea>
                </div>
                <div class="toolbar">
                    <div class="right">
                        <button class="send" @click="clickBtnSend">发送消息 ( Enter )</button>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
<script>
    var Vm = new Vue({
        el        : '#chat',
        data      : {
            websocketServer  : "<?= $server ?>",
            websocketInstance: undefined,
            // websocketInstance: new WebSocket(this.websocketServer + '?token=3d13ac8b27713846975db4ec24dc7195'),
            Reconnect        : false,
            ReconnectTimer   : null,
            HeartBeatTimer   : null,
            ReconnectBox     : null,
            currentUser      : {username: '-----', intro: '-----------', fd: 0, avatar: 0, id: 0},
            roomUser         : {},
            roomChat         : [],
            up_recv_time     : 0
        },
        created   : function () {
            this.connect();
        },
        mounted   : function () {
            var othis = this;
            var textInput = $('#text-input');
            textInput.on('keydown', function (ev) {
                if (ev.keyCode == 13 && ev.shiftKey) {
                    textInput.val(textInput.val() + "\n");
                    return false;
                } else if (ev.keyCode == 13) {
                    othis.clickBtnSend();
                    ev.preventDefault();
                    return false;
                }
            });
            $('.online-list').on('click', function () {
                $('.online_window').show();
                $('.windows_input').hide();
            });
            $('.times-icon').on('click', function () {
                $('.online_window').hide();
                $('.windows_input').show();
            });
            var input = document.getElementById("fileInput");
            input.addEventListener('change', readFile, false);

            function readFile() {
                var file = this.files[0];
                //判断是否是图片类型
                if (!/image\/\w+/.test(file.type)) {
                    alert("只能选择图片");
                    return false;
                }
                if (file.size > 1048576) {
                    alert('图片大小不能超过1MB');
                    return false;
                }
                var reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = function (e) {
                    othis.broadcastImageMessage(this.result)
                }
            }
        },
        methods   : {
            connect              : function () {
                var othis = this;
                var token = localStorage.getItem('token'); //登陆成功H5存储token值
                var websocketServer = this.websocketServer;
                var token = 'a90398e116e5fc9dc48e7dccd27676c6';
                if (token) {
                    websocketServer += '?token='+token
                } else {
                    //弹出未登录提示，重新登录
                }
                this.websocketInstance = new WebSocket(websocketServer);
                this.websocketInstance.onopen = function (ev) {
                    // 断线重连处理
                    if (othis.ReconnectBox) {
                        layer.close(othis.ReconnectBox);
                        othis.ReconnectBox = null;
                        clearInterval(othis.ReconnectTimer);
                    }
                    // 前端循环心跳 (1min)
                    othis.HeartBeatTimer = setInterval(function () {
                        othis.websocketInstance.send('PING');
                    }, 1000 * 30);
                    // 请求获取自己的用户信息和在线列表
                    othis.release('index', 'info');
                    othis.release('index', 'online');
                    othis.websocketInstance.onmessage = function (ev) {
                        try {
                            var data = JSON.parse(ev.data);
                            // console.log(data);
                            if (data.sendTime) {
                                if (othis.up_recv_time + 10 * 1000 > (new Date(data.sendTime)).getTime()) {
                                    othis.up_recv_time = (new Date(data.sendTime)).getTime();
                                    data.sendTime = null;
                                } else {
                                    othis.up_recv_time = (new Date(data.sendTime)).getTime();
                                }
                            }
                            if (data.code == 200) {
                                switch(data.msg) {
                                    case 'user':
                                        //othis.currentUser.intro = data.intro;
                                        othis.currentUser.avatar = data.data.wx_photo;
                                        othis.currentUser.fd = data.data.fd;
                                        othis.currentUser.nickname = data.data.nickname;
                                        othis.currentUser.id = data.data.id;
                                        // console.log(othis.currentUser)
                                        break;
                                        break;
                                }
                            }
                        } catch (e) {
                            // console.warn(e);
                        }
                    };
                    othis.websocketInstance.onclose = function (ev) {
                        //othis.doReconnect();
                    };
                    othis.websocketInstance.onerror = function (ev) {
                        //othis.doReconnect();
                    }
                }
            },
            doReconnect          : function () {
                var othis = this;
                clearInterval(othis.HeartBeatTimer);
                othis.ReconnectBox = layer.msg('已断开，正在重连...', {
                    scrollbar : false,
                    shade     : 0.3,
                    shadeClose: false,
                    time      : 0,
                    offset    : 't'
                });
                othis.ReconnectTimer = setInterval(function () {
                    othis.connect();
                }, 1000)
            },
            /**
             * 向服务器发送消息
             * @param controller 请求控制器
             * @param action 请求操作方法
             * @param params 携带参数
             */
            release              : function (controller, action, params) {
                controller = controller || 'index';
                action = action || 'action';
                params = params || {};
                var message = {cmd: controller+'-'+action, params: params}
                this.websocketInstance.send(JSON.stringify(message))
            },
            /**
             * 发送文本消息
             * @param content
             */
            broadcastTextMessage : function (content) {
                this.release('broadcast', 'roomBroadcast', {content: content, type: 'text'})
            },
            /**
             * 发送图片消息
             * @param base64_content
             */
            ImageMessage: function (base64_content) {
                this.release('broadcast', 'roomBroadcast', {content: base64_content, type: 'image'})
            },
            picture              : function () {
                var input = document.getElementById("fileInput");
                input.click();
            },
            /**
             * 点击发送按钮
             * @return void
             */
            clickBtnSend         : function () {
                var textInput = $('#text-input');
                var content = textInput.val();
                // var websocketServer = this.websocketServer;
                if (content.trim() !== '') {
                    // var token = 'a90398e116e5fc9dc48e7dccd27676c6';
                    // if (token) {
                    //     websocketServer += '?token='+token
                    // }
                    if (this.websocketInstance && this.websocketInstance.readyState === 1) {
                        this.broadcastTextMessage(content);
                        textInput.val('');
                    } else {
                        layer.tips('连接已断开', '.windows_input', {
                            tips: [1, '#ff4f4f'],
                            time: 2000
                        });
                    }
                } else {
                    layer.tips('请输入消息内容', '.windows_input', {
                        tips: [1, '#3595CC'],
                        time: 2000
                    });
                }
            },
            changeName           : function () {
                layer.prompt({title: '拒绝吃瓜，秀出你的昵称', formType: 0}, function (username, index) {
                    if (username) {
                        this.release('user', 'setUsername', {content: {'username' : username}, type: 'image'})
                    }
                    layer.close(index);
                });

            }
        },
        computed  : {
            currentCount() {
                return Object.getOwnPropertyNames(this.roomUser).length - 1;
            }
        },
        directives: {
            scrollBottom: {
                componentUpdated: function (el) {
                    el.scrollTop = el.scrollHeight
                }
            }
        }
    });
</script>
</body>
</html>