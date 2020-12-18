@extends('admin.auth.cateBase')

@section('body-title')
    <div class="layui-card-header">@if(isset($info)) {{$info['name']}} -- @endif编辑分类</div>
@endsection
@section('javascriptFooter')
    <script>

        layui.use('form', function(){
            var form = layui.form;
            form.val("form", {
                "name": "{{ $info['name'] }}"
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
                data.field.status = data.field.status ? 1 : 0;
                $.post('/setting/cate/edit/'+{{ $info['id'] }},data.field,function(info){
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