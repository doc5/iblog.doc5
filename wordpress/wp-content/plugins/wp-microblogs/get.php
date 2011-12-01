<?php
if (isset($_GET['oauth_token'])) {
    if (isset($_GET['oauth_verifier']))
        $verifier = $_GET['oauth_verifier'];
    else
        $verifier = $_GET['oauth_token'];
?>
    <script>
        if (parent.hideOAuthIframe) {
            parent.document.getElementById( 'token-or-username' ).value = '<?php echo $verifier; ?>';
            parent.hideOAuthIframe();
            parent.document.getElementById( 'ajax-submit' ).click();
        } else {
            window.opener.document.getElementById( 'token-or-username' ).value = '<?php echo $verifier; ?>';
            window.opener.document.getElementById( 'ajax-submit' ).click();
            window.close();
        }
        function test() {}
    </script>
    如果程序未能自动取得密匙，请手动复制：<br />
<?php echo $verifier; ?>
<?php
} else if (isset($_POST['type'])) {
    require_once( dirname(__FILE__) . '/../../../wp-includes/compat.php' );
    require_once( dirname(__FILE__) . '/class.oauth.php' );
    require_once( dirname(__FILE__) . '/functions.php' );
    $type = (int)$_POST['type'];
    $oauth = new MicroblogOAuth($type);

    $callback = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ) ? 'https://' : 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    switch ($type) {
        case 1 :
        case 5 :
        case 10 :
            $request_token = $oauth->getRequestToken();
            $url = $oauth->getAuthorizeURL($request_token['oauth_token'], false, $callback);
            break;
        case 2 :
            $request_token = $oauth->getRequestToken($callback);
            $url = $oauth->getAuthorizeURL($request_token['oauth_token'], false);
            break;
        case 3 :
            $request_token = $oauth->getRequestToken($callback);
            $url = $oauth->getAuthorizeURL($request_token['oauth_token'], true);
            break;
        case 4 :
            $request_token = $oauth->getRequestToken();
            $url = $oauth->getAuthorizeURL($request_token['oauth_token'], true, $callback);
            break;
    }

    $echo = array(
        'type' => $type,
        'request_token' => $request_token,
        'url' => $url
    );

    echo json_encode($echo);
}
?>