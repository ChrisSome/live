@extends('admin.auth.frontUserBase')

@section('body-title')
    <div class="layui-card-header">@if(isset($info)) {{$info['name']}} -- @endif添加用户</div>
@endsection
@section('javascriptFooter')
    <script>

        layui.use('form', function(){
            var form = layui.form;

            var form_field;

            function callback(data) {
                if(data.code != 0) {
                    layer.msg(info.msg);
                } else {
                    layer.msg('添加成功', {time:1000}, function(){
                       location.href='/user';
                    });
                }
            }

            form.verify({
                pwd: [
                    /^[\S]{6,12}$/
                    ,'密码必须6到15位，且不能出现空格'
                ],
                verify_pwd:function(value, item){
                    var pwd = $("input[name='pwd']").val();
                    if(pwd !== value) {
                        return '两次输入的密码不一致';
                    }
                },
                verify_email:function(value, item){
                    var _reg = /^[a-zA-Z0-9_-]+@([a-zA-Z0-9]+\.)+(com|cn|net|org)$/;
                    if (!_reg.test(value)) {
                        return '邮箱格式不合法';
                    }
                },
                verify_mobile: function(value, item) {
                    var _reg = /^1\d{10}$/;
                    if (!_reg.test(value)) {
                        return '号码格式不合法';
                    }
                }
            });

            //监听提交
            form.on('submit(submit)', function(data){
                data.field.status = data.field.status ? 1 : 0;
                form_field = data;
                post('/user/add', data.field, callback);
                return false;
            });
        });
    </script>
@endsection