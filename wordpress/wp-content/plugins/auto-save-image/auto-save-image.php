<?php
/* 
Plugin Name: Auto_Save_Image
Version: 2.2.1
Plugin URI: http://www.01on.com/a/497.html
Description: 自动保存远程图片
Author: Bai Yunshan
Author URI: http://www.01on.com
*/


add_action('admin_menu','Auto_Save_Image_addmenu');
add_filter('content_save_pre', 'Auto_Save_Image_savepost');
add_action('admin_footer','Auto_Save_Image_footerinserts');

function Auto_Save_Image_footerinserts() {
	if (str_replace("post-new.php","",$_SERVER['REQUEST_URI'])!=$_SERVER['REQUEST_URI']||str_replace("post.php","",$_SERVER['REQUEST_URI'])!=$_SERVER['REQUEST_URI']) {
	?>
		<script type="text/javascript"> 
		var isIE = (document.all && window.ActiveXObject && !window.opera) ? true : false;
		if(!isIE){//非IE浏览器直接初始化 
			add_auto_save_image(); 
		}else { 
			//IE下,防止浏览器提示“internet explore 无法打开internet站点 已终止操作”		 
			if (document.readyState=="complete"){ 
				add_auto_save_image(); 
			} else { 
				document.onreadystatechange=function(){ 
					if(document.readyState=="complete")add_auto_save_image(); 
				} 
			} 
		} 

		//添加表单元素
		function add_auto_save_image(){
			document.getElementById("titlediv").innerHTML = document.getElementById("titlediv").innerHTML + "<span id=\"check_post_title\"></span>";
			document.getElementById("titlediv").innerHTML = document.getElementById("titlediv").innerHTML + "<p align=\"right\"><input style=\"width:20px;\" type=\"checkbox\" name=\"Auto_Save_Image\" value=\"1\" id=\"checkbox\" <?php echo $_COOKIE["wordpress_Auto_Save_Image"];?> />自动保存远程图片&nbsp;&nbsp;&nbsp;<input style=\"width:20px;\" type=\"checkbox\" name=\"Auto_Remove_Link\" value=\"1\" id=\"checkbox\" <?php echo $_COOKIE["wordpress_Auto_Remove_Link"];?> />自动去除非本站链接<input type=\"hidden\" name=\"temp_ID2\" id=\"temp_ID2\"></p>";
			document.getElementById("temp_ID2").value = document.getElementById("post_ID").value;
			
			var isIE = (document.all && window.ActiveXObject && !window.opera) ? true : false;
			if(isIE)
				document.getElementById('title').attachEvent("onblur", check_re_title);
			else
				document.getElementById('title').addEventListener("blur", check_re_title, false);
		}

		function check_re_title(){
			var obj = document.getElementById('title');
			var title;
			
			title = trim(obj.value);
			title = title.replace(/</g, '&lt;');
			title = title.replace(/>/g, '&gt;');
			title = title.replace(/\"/g, '&#8221;');

			if(title!=""){
				document.getElementById("check_post_title").innerHTML = "正在检测标题《"+title+"》是否重复...";
				var xmlhttp;
				try{
					xmlhttp=new XMLHttpRequest();
					if (xmlhttp.overrideMimeType)
					{
						xmlhttp.overrideMimeType('text/xml');
					}
				}catch(e){
					xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
				}
				xmlhttp.onreadystatechange=function(){
					if (4==xmlhttp.readyState){
						if (200==xmlhttp.status){
							var data=xmlhttp.responseText;
							<?php $postid = (int)$_GET["post"];
							if($postid==0){ //新添加日志
							?>							
							var re=new RegExp("<td[^<>]{0,}><strong><a[^<>]{1,}>"+title+"</a></strong>"); //如果是2.5版以上
							<?php
							}else{ //修改日志
							?>
							data=data.replace(/<td[^<>]{0,}><strong><a[^<>]{1,}post=<?php echo $postid;?>\" title=\"[^<>]{1,}>[^<>]{1,}<\/a><\/strong>/g,"");
							//document.write(data);
							var re=new RegExp("<td[^<>]{0,}><strong><a[^<>]{1,}>"+title+"</a></strong>"); //如果是2.5版以上
							<?php
							}
							?>							
							if (re.exec(data)) {
								document.getElementById("check_post_title").innerHTML = "<font color=\"red\">标题《"+title+"》重复！</font>&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"edit.php?post_status=publish&s="+title+"&m=0&cat=0\" target=\"_blank\" title=\"查看重复的日志\">查看-><\/a>";
							}else{
								re=new RegExp("<td>(	|\s){0,}"+title+"(	|\s){0,}</td>"); //如果是2.3.3版
								if (re.exec(data)) {
									document.getElementById("check_post_title").innerHTML = "<font color=\"red\">标题《"+title+"》重复！</font>&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"edit.php?post_status=publish&s="+title+"&m=0&cat=0\" target=\"_blank\" title=\"查看重复的日志\">查看-><\/a>";
								}else{
									document.getElementById("check_post_title").innerHTML = "<font color=\"green\">标题《"+title+"》没有重复</font>";
								}
							}
						}else{
							alert("error");
						}
					}
				}
				xmlhttp.open("GET", "edit.php?s="+encodeURI(trim(obj.value))+"&post_status=publish&m=0&cat=0", true);
				xmlhttp.setRequestHeader('Content-type','text/html');
				xmlhttp.send(null);
			}else{
				document.getElementById("check_post_title").innerHTML = "";
			}
		}
		
		//去除字符串左右空格
		function trim(string)
		{
			return string.replace(/(^\s*)|(\s*$)/g, "");
		} 
		</script>
	<?php
	}	
}


//保存或修改文章时自动保存远程图片
function Auto_Save_Image_savepost($content){
	$Auto_Save_Image = get_option("Auto_Save_Image");
	$Auto_Save_Image = split("@@@",$Auto_Save_Image);
	$photo_markup = '1';
	$photo_markdown = '1';
	$photo_wwidth = '200'; //图片宽度大于此才加水印
	$photo_wheight = '120'; //图片高度大于此才加水印
	$photo_waterpos = $Auto_Save_Image[7];
	$photo_watertext = $Auto_Save_Image[3];
	$photo_fontsize = $Auto_Save_Image[4];
	$photo_fontcolor = $Auto_Save_Image[5];
	$photo_fontpath = $Auto_Save_Image[6];
	$photo_diaphaneity = $Auto_Save_Image[2];
	$photo_markimg = "../".$Auto_Save_Image[1];
	$photo_savepath = $Auto_Save_Image[0];
	

	//保存图片
	if($_POST['Auto_Save_Image']=="1"){
		setcookie('wordpress_Auto_Save_Image', ' checked="checked" ', time()+3600*24*366);
		require_once(dirname(__FILE__) . "/inc_photograph.php");
		require_once("../wp-includes/class-snoopy.php");
		$snoopy_Auto_Save_Image = new Snoopy;
		// begin to save pic;
		$img_array = array();
		if ( !empty( $_REQUEST['post_title'] ) )
			$post_title = wp_specialchars( stripslashes( $_REQUEST['post_title'] ));
		$content1 = stripslashes($content);
		if (get_magic_quotes_gpc()) $content1 = stripslashes($content1);
		preg_match_all("/ src=(\"|\'){0,}(http:\/\/(.+?))(\"|\'|\s)/is",$content1,$img_array);
		$img_array = array_unique(dhtmlspecialchars($img_array[2]));
		foreach ($img_array as $key => $value){
			set_time_limit(180); //每个图片最长允许下载时间,秒
			if(str_replace(get_bloginfo('url'),"",$value)==$value&&str_replace(get_bloginfo('home'),"",$value)==$value){
				$fileext = substr(strrchr($value,'.'),1);
				$fileext = strtolower($fileext);
				if($fileext==""||strlen($fileext)>4)$fileext = "jpg";
				$savefiletype = array('jpg','gif','png','bmp');
				if (in_array($fileext, $savefiletype)){ 
					if($snoopy_Auto_Save_Image->fetch($value)){
						$get_file = $snoopy_Auto_Save_Image->results;
					}else{
						echo "error fetching file: ".$snoopy_Auto_Save_Image->error."<br>";
						echo "error url: ".$value;
						die();
					}
					$filetime = time();
					$filepath = "/wp-content/uploads/".$photo_savepath.date("Y",$filetime)."/".date("m",$filetime)."/";//图片保存的路径目录
					!is_dir("..".$filepath) ? mkdirs("..".$filepath) : null; 
					$filename = date("His",$filetime).random(3);

					$fp = @fopen("..".$filepath.$filename.".".$fileext,"w");
					@fwrite($fp,$get_file);
					fclose($fp);

					//添加水印开始
					$srcFile = "..".$filepath.$filename.".".$fileext;
					$info = "";
					$srcInfo = GetImageSize($srcFile,$info);
					$srcFile_w    = $srcInfo[0];
					$srcFile_h    = $srcInfo[1];
					if($srcFile_w > $photo_wwidth && $srcFile_h > $photo_wheight){  
						$trueMarkimg = $photo_markimg;
						if(!file_exists($trueMarkimg) || empty($photo_markimg) || $photo_markimg=='../' || $photo_watertext !='') $trueMarkimg = "";
						ImgWaterMark($srcFile,$photo_waterpos,$trueMarkimg,$photo_watertext,$photo_fontsize,$photo_fontcolor,$photo_diaphaneity,$photo_fontpath);
					}
					//添加水印结束
			
					$wp_filetype = wp_check_filetype( $filename.".".$fileext, false );
					$type = $wp_filetype['type'];
					$post_id = (int)$_POST['temp_ID2'];
					$title = $post_title;
					$url = get_bloginfo('url').$filepath.$filename.".".$fileext;
					$file = $_SERVER['DOCUMENT_ROOT'].$filepath.$filename.".".$fileext;
					
					//添加数据库记录
					$attachment = array(
						'post_type' => 'attachment',
						'post_mime_type' => $type,
						'guid' => $url,
						'post_parent' => $post_id,
						'post_title' => $title,
						'post_content' => '',
					);
					$id = wp_insert_attachment($attachment, $file, $post_parent);
					if ( !is_wp_error($id) ) {
						//这里会生成缩略图，不要了
						//wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
					}
					
					$content1 = str_replace($value,get_bloginfo('url').$filepath.$filename.".".$fileext,$content1); //替换文章里面的图片地址
				}
			}
		}
		$content = AddSlashes($content1);
		// end save pic;
	}else{
		setcookie('wordpress_Auto_Save_Image', '', time()+3600*24*366);
	}

	//去除非本站链接
	if($_POST['Auto_Remove_Link']=="1"){
		setcookie('wordpress_Auto_Remove_Link', ' checked="checked" ', time()+3600*24*366); //366天后过期
		$basehost = get_bloginfo('url');
		$body = $content;
		$body = stripslashes($body);
		$body = str_replace($basehost,'#basehost#',$body);
		$body = preg_replace('/<a[ \t\r\n]{1,}href=["\']{0,}http:\/\/[^\/][^>]*>(.*?)<\/a>/isU','\1',$body); 
		$body = str_replace('#basehost#',$basehost,$body);
		$body = AddSlashes($body);
		$content = $body;
	}else{
		setcookie('wordpress_Auto_Remove_Link', '', time()+3600*24*366);
	}
	remove_filter('content_save_pre', 'Auto_Save_Image_savepost');
	return $content;
}

//添加后台菜单
function Auto_Save_Image_addmenu() {
	add_options_page('Auto_Save_Image Options', 'Auto_Save_Image', 8, __FILE__,'Auto_Save_Image_setoption');
	add_option('Auto_Save_Image', 'auto_save_image/@@@wp-content/plugins/auto-save-image/watermark.png@@@80@@@'.get_bloginfo('name').'@@@12@@@#000000@@@wp-content/plugins/auto-save-image/simsun.ttc@@@9@@@10@@@10'); 
	//图片保存路径@@@水印图片路径@@@水印图片透明度@@@水印文字内容@@@水印文字大小@@@文字颜色@@@水印文字字体路径@@@水印位置@@@x方向上的偏移量@@@y方向上的偏移量
}

//设置页面
function Auto_Save_Image_setoption(){
		if (!empty($_POST['Auto_Save_Image_update'])) 
		{
			$Auto_Save_Image = $_POST['Auto_Save_Image_imagepath']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkimage']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkimagediaphaneity']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarktext']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarktextsize']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarktextcolor']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkfont']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkposition']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkposition_x']."@@@";
			$Auto_Save_Image .= $_POST['Auto_Save_Image_watermarkposition_y'];
			//图片保存路径@@@水印图片路径@@@水印文字内容@@@水印文字大小@@@水印文字颜色@@@水印文字字体路径@@@水印位置@@@x方向上的偏移量@@@y方向上的偏移量
			update_option("Auto_Save_Image",$Auto_Save_Image);
			echo '<div class="updated"><strong><p>保存成功</p></strong></div>';
		}
?>
		<div class=wrap>
        	<h2>Auto_Save_Image 设置</h2>
		  <form method="post">
				<?php
                $Auto_Save_Image = get_option("Auto_Save_Image");
                $Auto_Save_Image = split("@@@",$Auto_Save_Image);
                ?>
                <table class="form-table">
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_imagepath">远程图片保存目录</label></th>
                        <td><?php bloginfo('url') ?>/wp-content/uploads/<input name="Auto_Save_Image_imagepath" id="Auto_Save_Image_imagepath" value="<?php echo $Auto_Save_Image[0] ?>">&nbsp;(请以"/"结尾,可以为空)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkimage">水印图片路径</label></th>
                        <td><?php bloginfo('url') ?>/<input name="Auto_Save_Image_watermarkimage" id="Auto_Save_Image_watermarkimage" value="<?php echo $Auto_Save_Image[1] ?>" size="50">
                        &nbsp;(如果为空或不存在,则不使用图片水印)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkimagediaphaneity">水印图片透明度</label></th>
                        <td><input name="Auto_Save_Image_watermarkimagediaphaneity" id="Auto_Save_Image_watermarkimagediaphaneity" value="<?php echo $Auto_Save_Image[2] ?>" >&nbsp;(请用英文半角小写输入1-100之间的数字)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarktext">水印文字内容</label></th>
                      <td><input name="Auto_Save_Image_watermarktext" id="Auto_Save_Image_watermarktext" value="<?php echo $Auto_Save_Image[3] ?>">
                        &nbsp;(如果不为空,则使用文字水印;如果为空,则使用图片水印)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarktextsize">水印文字大小</label></th>
                      <td><input name="Auto_Save_Image_watermarktextsize" id="Auto_Save_Image_watermarktextsize" value="<?php echo $Auto_Save_Image[4] ?>">
                        (请输入英文半角小写数字)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarktextcolor">水印文字颜色</label></th>
                        <td><input name="Auto_Save_Image_watermarktextcolor" id="Auto_Save_Image_watermarktextcolor" value="<?php echo $Auto_Save_Image[5] ?>"></td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkfont">水印文字字体路径</label></th>
                      <td><?php bloginfo('url') ?>/<input name="Auto_Save_Image_watermarkfont" id="Auto_Save_Image_watermarkfont" value="<?php echo $Auto_Save_Image[6] ?>" size="50">
                        &nbsp;(如果为空或不存在,则不使用文字水印)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkfont">水印位置</label></th>
                        <td><input type="text" name="Auto_Save_Image_watermarkposition" id="Auto_Save_Image_watermarkposition" value="<?php echo $Auto_Save_Image[7] ?>" />&nbsp;(请输入英文半角小写数字,0为随机,其他位置值如下：)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"></th>
                      <td>1&nbsp;&nbsp;2&nbsp;&nbsp;3<br />
                        4&nbsp;&nbsp;5&nbsp;&nbsp;6<br />
                        7&nbsp;&nbsp;8&nbsp;&nbsp;9</td>
                    </tr>
                    <!--
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkposition_x">水印x方向上的偏移量</label></th>
                        <td><input type="text" name="Auto_Save_Image_watermarkposition_x" id="Auto_Save_Image_watermarkposition_x" value="<?php echo $Auto_Save_Image[8] ?>" />&nbsp;(请输入英文半角小写数字)</td>
                    </tr>
                    <tr valign="middle">
                        <th scope="row"><label for="Auto_Save_Image_watermarkposition_y">水印y方向上的偏移量</label></th>
                        <td><input type="text" name="Auto_Save_Image_watermarkposition_y" id="Auto_Save_Image_watermarkposition_y" value="<?php echo $Auto_Save_Image[9] ?>" />&nbsp;(请输入英文半角小写数字)</td>
                    </tr>
                    -->
                </table>	
                
                		

			<p class="submit"><input type="submit" name="Auto_Save_Image_update" value=" 保存 " /></p>
			</form>
		</div>
<?php
}

//用到的函数
function dhtmlspecialchars($string) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = dhtmlspecialchars($val);
		}
	}else{
		$string = str_replace('&', '&', $string);
		$string = str_replace('"', '"', $string);
		$string = str_replace('<', '<', $string);
		$string = str_replace('>', '>', $string);
		$string = preg_replace('/&(#\d;)/', '&\1', $string);
	}
	return $string;
}

function random($length) {
	$hash = '';
	$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
	$max = strlen($chars) - 1;
	mt_srand((double)microtime() * 1000000);
	for($i = 0; $i < $length; $i++) {
	  $hash .= $chars[mt_rand(0, $max)];
	}
	return $hash;
}
 
function mkdirs($dir)
{
	if(!is_dir($dir))
	{
		mkdirs(dirname($dir));
		mkdir($dir);
	}
	return ;
}  

?>