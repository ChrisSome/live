@extends('admin.auth.ruleBase')

@section('body-title')
  <div class="layui-card-header">@if(isset($info)) {{$info['name']}} -- @endif添加权限</div>
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
    @if(isset($id))
        post('/rule/add/{{$id}}', data.field, callback);
    @else
        post('/rule/add', data.field, callback);
    @endif

    return false;
  });
});
</script>
@endsection