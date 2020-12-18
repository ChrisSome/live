@extends('admin.auth.systemBase')

@section('body-title')
    <div class="layui-card-header">@if(isset($info))  -- @endif编辑分类</div>
@endsection
@section('javascriptFooter')
    <script>

        layui.use('form', function(){
            var form = layui.form;
            form.val("form", {
                "sys_key": "{{ $info['sys_key'] }}"
                ,"sys_value": "{{ $info['sys_value'] }}"
            });
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
                $.post('/setting/sys/edit/'+{{ $info['id'] }},data.field,function(info){
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