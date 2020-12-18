@extends('admin.auth.systemBase')

@section('body-title')
    <div class="layui-card-header">@if(isset($info)) {{$info['name']}} -- @endif添加分类</div>
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
                        location.href = '/setting/sys';
                    });
                }
            }

            //监听提交
            form.on('submit(submit)', function(data){
                form_field = data;
                post('/setting/sys/add', data.field, callback);

                return false;
            });
        });
    </script>
@endsection