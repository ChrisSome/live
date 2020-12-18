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
								<input type="text" name="title" required  lay-verify="required" placeholder="请输入标题" autocomplete="off" class="layui-input">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">所属分类</label>
							<div class="layui-input-block">
								<select name="cate_id" id="cate_od">
									<option value="">请选择</option>
									@foreach ($cates as $key => $value)
										<option value="{{ $key }}" @if(isset($info) && $info['cate_id'] == $key) selected @endif>
											{{ $value }}
										</option>
									@endforeach
								</select>
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">是否启动</label>
							<div class="layui-input-block">
								<input type="checkbox" name="status" lay-skin="switch" checked lay-text="是|否">
							</div>
						</div>
						<div class="layui-form-item">
							<label class="layui-form-label">内容</label>
							<div class="layui-input-block">
								<textarea name="content" id="content_message" cols="30" rows="10">
									{{isset($info) ? $info['content'] : ''}}
								</textarea>
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