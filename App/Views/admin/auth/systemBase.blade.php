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
							<label class="layui-form-label">键</label>
							<div class="layui-input-block">
								<input type="text" name="sys_key" required  lay-verify="required" placeholder="请输入键" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">值</label>
							<div class="layui-input-block">
								<input type="text" name="sys_value" required  lay-verify="required" placeholder="请输入值" autocomplete="off" class="layui-input">
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