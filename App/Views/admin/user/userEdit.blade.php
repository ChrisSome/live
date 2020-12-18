@extends('admin.auth.frontUserBase')

@section('javascriptFooter')
    <script>

        layui.use('form', function(){
            var form = layui.form;

            form.val("form", {
                "nickname": "{{ $info['nickname'] }}"
                ,"mobile": "{{ $info['mobile'] }}"
                ,"status": {{ $info['status'] }}
            });

            form.verify({
                username: function(value) {
                    if (true) {
                        return  '用户已存在';
                    }
                },
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
                    } else  {
                        $.post('/user/is_repeat', {'id': {{$info['id']}}, 'value': value, 'type': 'email'}, function (res) {
                            console.log(res);
                        })
                    }
                }
            });
            //监听提交
            form.on('submit(submit)', function(data){
                data.field.status = data.field.status ? 1 : 0;
                $.post('/user/edit/'+{{ $info['id'] }},data.field,function(info){
                    if(info.code != 0) {
                        layer.msg(info.msg);
                    } else {
                        layer.msg('编辑成功',{time:1000},function(){
                            parent.layer.close(parent.layer.getFrameIndex(window.name));
                            parent.refresh();
                        });
                    }
                });
                return false;
            });
        });
    </script>
@endsection