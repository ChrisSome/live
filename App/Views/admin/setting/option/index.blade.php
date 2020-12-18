@extends('layouts.admin')

@section('stylesheet')
<link rel="stylesheet" href="/layui/css/layui.css"  media="all">
@endsection

@section('body')

<table class="layui-hide" id="test" lay-filter="test"></table>

{{--<script type="text/html" id="toolbarDemo">
  <div class="layui-btn-container">
    <button class="layui-btn layui-btn-sm" lay-event="getCheckData">获取选中行数据</button>
    <button class="layui-btn layui-btn-sm" lay-event="getCheckLength">获取选中数目</button>
    <button class="layui-btn layui-btn-sm" lay-event="isAll">验证是否全选</button>
  </div>
</script>--}}

<script type="text/html" id="barDemo">
  <a class="layui-btn layui-btn-xs" lay-event="edit">编辑</a>
  <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
</script>
@endsection


@section('javascriptFooter')

<script src="/layui/layui.js" charset="utf-8"></script>
<script>
layui.use('table', function(){
  var table = layui.table;

  datatable = table.render({
    elem: '#test'
    ,url:'/setting/option/list'
    ,toolbar: '#toolbarDemo'
    ,title: '投诉建议表'
    ,cols: [[
      {type: 'checkbox', fixed: 'left'}
      ,{field:'id', title:'ID', width:80, fixed: 'left', unresize: true, sort: true}
      ,{field:'nickname', title:'昵称', width:120, edit: 'text'}
      ,{field:'content', title:'内容', width: 400, edit: 'text', templet: function(res){
        return '<em>'+ res.content +'</em>'
      }}
      ,{field:'status', title:'状态', width:80, edit: 'text', sort: true, templet: function(res) {
          return res.status == 0 ? '待审核' : (res.status == 1 ? '已回复' : '已废弃')
        }}
      ,{field:'admin_name', title:'操作员', width:100}
      ,{field:'reply', title:'回复内容', width: 300}
      ,{field:'created_at', title:'创建时间', width:200, sort: true}
      ,{field:'created_at', title:'更新时间', width:180, sort: true}
      ,{fixed: 'right', title:'操作', toolbar: '#barDemo', width:150}
    ]]
  ,code: 0
  ,msg: ""
  ,data: []
    ,page: true
  });
  window.refresh = function()
  {
    datatable.reload();
  }
  //头工具栏事件
  table.on('toolbar(test)', function(obj){
    var checkStatus = table.checkStatus(obj.config.id);
    switch(obj.event){
      case 'getCheckData':
        var data = checkStatus.data;
        layer.alert(JSON.stringify(data));
      break;
      case 'getCheckLength':
        var data = checkStatus.data;
        layer.msg('选中了：'+ data.length + ' 个');
      break;
      case 'isAll':
        layer.msg(checkStatus.isAll ? '全选': '未全选');
      break;
    };
  });

  //监听行工具事件
  table.on('tool(test)', function(obj){
    var data = obj.data;
    switch(obj.event){
      case 'del':
        layer.confirm('真的删除行么', function(index){
          $.post('/setting/option/del/' + data.id ,'',function(data){
            layer.close(index);
            if(data.code != 0) {
              layer.msg(data.msg);
            } else {
              obj.del();
            }
          });
        });
        break;
      case 'edit':
        layer.open({
          title: '回复内容'
          ,type: 2
          ,content: '/setting/option/edit/' + data.id
          ,area:['800px', '600px']
        });
        break;
    }
  });
});
</script>
@endsection