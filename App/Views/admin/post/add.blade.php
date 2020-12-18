@extends('layouts.admin')

@section('body')
    <div class="layui-card">
        @yield('body-title')
        <div class="layui-card-body">
            <div class="layui-container">
                <div class="layui-row">
                    <div class="layui-col-md9">
                        <form class="layui-form" action="" lay-filter="form">
                            <div class="layui-form-item">
                                <label class="layui-form-label">标题</label>
                                <div class="layui-input-block">
                                    <input type="text" name="title" placeholder="备注" autocomplete="off" class="layui-input">
                                </div>
                            </div>

                            <div class="layui-form-item">
                                <label class="layui-form-label">内容</label>
                                <div class="layui-input-block">
								<textarea name="content" id="content_message" cols="30" rows="10" disabled>

								</textarea>
                                </div>
                            </div>

                            <div class="layui-form-item">
                                <button class="layui-btn upload" lay-data="{url: '/api/user/upload/'}">上传图片</button>
                            </div>
                            <div class="layui-form-item">
                                <div class="layui-input-block">
                                    <button class="layui-btn" lay-submit lay-filter="submit">立即提交</button>
                                    <button type="reset" class="layui-btn layui-btn-primary">重置</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection



@section('javascriptFooter')
    <script src="/layui/layui.all.js"></script>
    <script>

        layui.upload.render({
            elem: '#id'
            ,url: '/api/user/upload/'
            ,auto: false //选择文件后不自动上传
            ,bindAction: '#testListAction' //指向一个按钮触发上传
            ,choose: function(obj){
                //将每次选择的文件追加到文件队列
                var files = obj.pushFile();

                //预读本地文件，如果是多文件，则会遍历。(不支持ie8/9)
                obj.preview(function(index, file, result){
                    console.log(index); //得到文件索引
                    console.log(file); //得到文件对象
                    console.log(result); //得到文件base64编码，比如图片

                    //obj.resetFile(index, file, '123.jpg'); //重命名文件名，layui 2.3.0 开始新增

                    //这里还可以做一些 append 文件列表 DOM 的操作

                    //obj.upload(index, file); //对上传失败的单个文件重新上传，一般在某个事件中使用
                    //delete files[index]; //删除列表中对应的文件，一般在某个事件中使用
                });
            }
        });
        //展示下拉框
        layui.use('form', function(){
            var layedit = layui.layedit;

            layedit.set({
                uploadImage: {
                    url: '/api/user/upload' //接口url
                    ,type: 'post' //默认post
                }
            });
            var _index = layedit.build('content_message');
            var form = layui.form;
            var form_field;


            function callback(data) {
                if(data.code != 0) {
                    layer.msg(info.msg);
                } else {
                    layer.msg('编辑成功', {time:1000}, function(){

                    });
                }
            }

            //监听提交
            form.on('submit(submit)', function(data){
                form_field = data;
                data.field.content = layui.layedit.getContent(_index);
                $.post('/user/post/add/2',data.field,function(info){
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