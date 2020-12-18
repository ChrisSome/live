<!doctype html>
<html lang="zh-Hans">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://cdn.staticfile.org/amazeui/2.7.2/css/amazeui.min.css">
    <link rel="stylesheet" href="/css/front_login.css?v=190527">
    <script src="https://cdn.staticfile.org/jquery/3.4.1/jquery.min.js"></script>
    <title>登录--野猫体育</title>
</head>
<body>

<!-- 登录框体 -->
<div class="block-box">
    <div class="main-title">野猫体育</div>
    <div class="sub-title">野猫体育直播</div>
    <form class="am-form" style="margin-top: 80px;">
        <div class="am-form-group am-input-group">
            <span class="am-input-group-label"><i class="am-icon-at am-icon-fw"></i></span>
            <input type="email" class="am-form-field" id="mobile" placeholder="输入手机号码" required/>
        </div>
        <div class="am-form-group am-input-group">
            <span class="am-input-group-label"><i class="am-icon-code am-icon-fw"></i></span>
            <input type="text" id="code" name="code" class="am-form-field" placeholder="输入验证码">
            <span class="am-input-group-label" id="sendValidate" style="cursor:pointer;">获取验证码</span>
        </div>
        <div class="am-form-group">
            <button type="button" class="am-btn am-btn-primary am-btn-block" style="margin-top: 0px;">开始畅聊</button>
        </div>
        <div class="am-form-group" style="text-align: center;margin-top: 130px;">
            <a href="/api/register">没有微聊账号 马上注册一个</a>
        </div>
    </form>
</div>

</body>
<script>
    $(function () {

        $(document).on('click', '#sendValidate', function () {
            var _mobile = $('#mobile').val();
            if (!_mobile) {
                alert('手机不能为空');

                return  ;
            }
            var _reg = /^1\d{10}$/;
            if (!_reg.test(_mobile)) {
                alert('手机号格式不正确');

                return  ;
            }
            $.post('/api/user/get-phone-code', {"mobile": _mobile}, function (res) {
                alert(res.msg)
            })
        })
        $(document).on('click', '.am-btn-block', function () {
            var _mobile = $('#mobile').val();
            var _code = $('#code').val();

            if (!_mobile) {
                alert('手机不能为空');

                return  ;
            }

            if (!_code) {
                alert('验证码不能为空');

                return ;
            }
            $.post('/api/user/doLogin', {"mobile": _mobile, "code": _code}, function (res) {
                if (res.code == 0 && res.msg == 'OK') {
                    localStorage.token = res.data.data.token;
                    location.href='/api/broad/list';
                } else {
                    alert(res.msg)
                }
                console.log(res);
            })
            /*  $.ajax({
                  url: '/login/doLogin',
                  //请求方式
                  type : "post",
                  //请求的媒体类型
                  contentType: "application/json;charset=UTF-8",
                  data: {"email": _email, "password": _password},
                  success: function (res) {
                      console.log(res);
                  }
              })*/
        })
    })
</script>
</html>