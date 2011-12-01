<?php

class MicroblogBasic {

    function __construct($microblog, $user, $pass = NULL) {
        $this->curl = curl_init();
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        if (!empty($pass))
            curl_setopt($this->curl, CURLOPT_USERPWD, "$user:$pass");
        $this->user = $user;
        if ($microblog == 1)
            $this->postdata['source'] = '2360171537';       // AppKey of Sina Weibo
        else
            $this->postdata = array();
    }

    function get($url, $params = array()) {
        $params = array_merge($this->postdata, $params);
        if (!empty($params))
            $url .= '?' . http_build_query($params);
//            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($params));

        curl_setopt($this->curl, CURLOPT_URL, $url);

        $ret = curl_exec($this->curl);

        return json_decode($ret, true);
    }

    function post($url, $params = array()) {
        $params = array_merge($this->postdata, $params);
        if (!empty($params))
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($params));

        curl_setopt($this->curl, CURLOPT_POST, true);
        curl_setopt($this->curl, CURLOPT_URL, $url);

        $ret = curl_exec($this->curl);

        return json_decode($ret, true);
    }

    function __destruct() {
        curl_close($this->curl);
    }

}
