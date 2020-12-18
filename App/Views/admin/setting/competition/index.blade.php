@extends('layouts.admin')

@section('body')
    <div class="white p20">
        <table class="layui-hide" id="test" lay-filter="test"></table>

        <!-- 表头 -->
        <script type="text/html" id="toolbarDemo">
            <div class="layui-btn-container" style="margin-top: 10px;">
                <form class="layui-form"  action="" method="post" lay-filter="form">
                    <div class="layui-row">

                        <div class="layui-col-md1">
                            <div class="layui-inline">
                                <input class="layui-input layui-btn-sm" name="short_nme_zh" id="short_nme_zh" autocomplete="off" placeholder="赛事名">
                            </div>
                        </div>

                        <div class="layui-col-md2">
                            <button class="layui-btn layui-btn-sm searchBtn" type="button">添加</button>
                            <button class="layui-btn layui-btn-sm searchBtn1" type="button">添加至热门</button>
                            <button class="layui-btn layui-btn-sm saveBtn" type="button">保存</button>
                        </div>
                    </div>
                </form>
            </div>
        </script>

        <script type="text/html" id="barDemo">

            @if($role_group->hasRule('user.post.del'))
            <a class="layui-btn layui-btn-danger layui-btn-xs" lay-event="del">删除</a>
            @endif
        </script>


    </div>
@endsection


@section('javascriptFooter')
    <script>
        layui.use('laydate', function(){
            var laydate = layui.laydate;

            //执行一个laydate实例
            laydate.render({
                elem: '#time' //指定元素
                ,range: true
                ,theme: 'grid'
                ,calendar: true
            });

        });
        var datatable;
        $(document).on('click','.searchBtn',function () {
            var short_name_zh = $('#short_nme_zh').val();
            $.get('/core/competition/info?short_name_zh=' + short_name_zh, '', function (data) {
                // alert(data);
                // layer.msg(data);
                if (data.code != 0) {

                    layer.msg(' 赛事不存在');

                    // form.render();
                } else {

                    layer.confirm('确定添加此条赛事 ID:' + data.data.competition_id + ',简称：' + data.data.short_name_zh, function (index) {

                        $.post('/core/competition/add?cid=' + data.data.competition_id, '', function (data) {
                            layer.close(index);
                            if (data.code != 0) {
                                layer.msg('添加失败');
                            } else {
                                datatable.reload({

                                })
                            }
                        });
                    });
                }
            })

        });
        $(document).on('click','.searchBtn1',function () {
            var short_name_zh = $('#short_nme_zh').val();
            $.get('/core/competition/info?is_hot=1&short_name_zh=' + short_name_zh, '', function (data) {
                // alert(data);
                // layer.msg(data);
                if (data.code != 0) {

                    layer.msg(' 赛事不存在');

                    // form.render();
                } else {
                    if (data.is_hot == 1) {
                        layer.confirm('确定添加此条赛事至热门 ID:' + data.data.competition_id + ',简称：' + data.data.short_name_zh, function (index) {

                            $.post('/core/competition/add?is_hot=1&cid=' + data.data.competition_id, '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg('添加失败');
                                } else {
                                    datatable.reload({

                                    })
                                }
                            });
                        });
                    }

                }
            })

        });

        $(document).on('click','.saveBtn',function () {
            $.post('/core/competition/save', '', function (data) {
                // alert(data);
                // layer.msg(data);
                if (data.code != 0) {

                    layer.msg('保存失败');

                    // form.render();
                } else {
                    layer.msg('保存成功');
                }
            })

        });
        layui.use('table', function () {
            var table = layui.table, form = layui.form;

            datatable = table.render({
                elem: '#test'
                , url: '/core/competition/list'
                , method: 'get'
                , toolbar: '#toolbarDemo'
                , title: '推荐赛事列表'
                , cols: [[
                    {field: 'competition_id', title: '赛事ID', width: 80, fixed: 'left'}
                    , {field: 'short_name_zh', title: '赛事简称', width: 100}
                ]]
                ,	parseData:function(res){
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
                    // $('#mobile').val(res.params.mobile)
                    return {
                        code: res.code,
                        msg:res.status,
                        count:res.count, //总页数，用于分页
                        data:res.data
                    }
                }
                , defaultToolbar: []
                , page: true
            });








        });layui.use('table', function () {
            var table = layui.table, form = layui.form;

            datatable = table.render({
                elem: '#test'
                , url: '/core/competition/list'
                , method: 'get'
                , toolbar: '#toolbarDemo'
                , title: '赛事列表'
                , cols: [[
                    {field: 'competition_id', title: 'ID', width: 80, fixed: 'left'}
                    , {field: 'short_name_zh', title: '赛事名称', width: 200}
                    , {fixed: 'right', title: '操作', toolbar: '#barDemo', width: 290}

                ]]
                ,	parseData:function(res){
                    //这个函数非常实用，是2.4.0版本新增的，当后端返回的数据格式不符合layuitable需要的格式，用这个函数对返回的数据做处理，在2.4.0版本之前，只能通过修改table源码来解决这个问题
                    layui.use('laydate', function(){
                        var laydate = layui.laydate;

                        //执行一个laydate实例
                        laydate.render({
                            elem: '#time' //指定元素
                            ,range: true
                            ,theme: 'grid'
                            ,calendar: true
                            ,sInitValue: true

                        });

                    });
                    return {
                        code: res.code,
                        msg:res.status,
                        count:res.count, //总页数，用于分页
                        data:res.data
                    }
                }
                , defaultToolbar: []
                , page: true
            });



            //监听行工具事件
            table.on('tool(test)', function (obj) {
                var data = obj.data;

                switch (obj.event) {
                    case 'del':

                        layer.confirm('确定删除此条赛事？', function (index) {
                            $.post('/core/competition/del?cid=' + data.competition_id, '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'set_top':
                        layer.confirm('确定置顶吗', function (index) {
                            $.post('/user/post/setTop/' + data.id, '', function (data) {
                                layer.close(index);
                                if (data.code != 0) {
                                    layer.msg(data.msg);
                                } else {
                                    obj.del();
                                }
                            });
                        });
                        break;
                    case 'comment':
                        layer.open({
                            title: '查看评论'
                            , type: 2
                            , content: '/user/post/comment/' + data.id
                            , area: ['800px', '620px']
                        });
                        break;
                    case 'edit':
                        layer.open({
                            title: '编辑权限'
                            , type: 2
                            , content: '/user/post/edit/' + data.id
                            , area: ['800px', '620px']
                        });
                        break;
                }
            });




        });
    </script>
@endsection
