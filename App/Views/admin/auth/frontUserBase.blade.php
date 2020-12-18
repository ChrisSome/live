@extends('layouts.admin')

@section('body')
<div class="layui-card">
    @yield('body-title')
    <div class="layui-card-body">
        <div class="layui-container">
            <div class="layui-row">
                <div class="layui-col-md10">
                    <form class="layui-form" action="" lay-filter="form">
                        <div class="layui-form-item">
                            <label class="layui-form-label">昵称</label>
                            <div class="layui-input-block">
                                <input type="text" name="nickname" required  lay-verify="required" placeholder="请输入登陆名" autocomplete="off" class="layui-input">
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">手机号码</label>
                            <div class="layui-input-block">
                                <input type="text" name="mobile" required lay-verify="required|verify_mobile" placeholder="请输入号码" autocomplete="off" class="layui-input">
                            </div>
                        </div>

                        <div class="layui-form-item">
							<label class="layui-form-label">是否启动</label>
							<div class="layui-input-block">
								<input type="checkbox" name="status" lay-skin="switch" checked lay-text="是|否">
							</div>
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