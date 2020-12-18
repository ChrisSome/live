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
							<label class="layui-form-label">名称</label>
							<div class="layui-input-block">
								<input type="text" name="name" required  lay-verify="required" placeholder="请输入名称" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">播放地址</label>
							<div class="layui-input-block">
								<input type="text" name="url" required lay-verify="required" placeholder="请输入播放源地址" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">接入方法</label>
							<div class="layui-input-block">
								<input type="text" name="func" required lay-verify="required" placeholder="接入方法" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">是否启动</label>
							<div class="layui-input-block">
								<input type="checkbox" name="status" lay-skin="switch" checked lay-text="是|否">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">封面图片</label>
							<div class="layui-input-block">
								<button type="button" class="layui-btn" id="test1">
									<i class="layui-icon">&#xe67c;</i>上传图片
								</button>
								<input type="hidden" name="cover_img"/>
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