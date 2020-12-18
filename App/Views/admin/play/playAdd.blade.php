@extends('admin.auth.playBase')

@section('body-title')
    <div class="layui-card-header">@if(isset($info)) {{$info['name']}} -- @endif添加播放源</div>
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
                        form_field.form.reset();
                    });
                }
            }

            //监听提交
            form.on('submit(submit)', function(data){
                data.field.status = data.field.status ? 1 : 0;
                form_field = data;
                post('/core/play/add', data.field, callback);

                return false;
            });
        });

        layui.use('upload', function(){
            var upload = layui.upload;

            //执行实例
            var uploadInst = upload.render({
                elem: '#test1' //绑定元素
                ,url: '/upload' //上传接口
                ,done: function(res){
                    //上传完毕回调
                }
                ,accept: 'images' //允许上传的文件类型
                ,size: 1024 //最大允许上传的文件大小
                ,error: function(){
                    //请求异常回调
                }
            });
        });
    </script>
@endsection