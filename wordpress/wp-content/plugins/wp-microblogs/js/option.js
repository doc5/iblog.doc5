jQuery('document').ready( function($) {
    function Microblog( type, nick, suspend, authtype ) {
        if ( !( Microblog.AUTH_TYPE[type] & authtype ) )
            throw new Error( 'Invaild auth type.' );
        this.type = type;
        this.nick = nick;
        this.suspend = suspend;
        this.authtype = authtype;
    }

    Microblog.AUTH_NAME = {
        1 : '不认证',
        2 : 'OAuth',
        4 : 'XAuth',
        8 : 'Basic Auth'
    }

    Microblog.AUTH_TYPE = {
        1 : 2,
        2 : 2,
        3 : 3,
        4 : 2,
        5 : 10,
        6 : 9,
        7 : 9,
        8 : 9,
        9 : 9,
        10 : 2
    };

    Microblog.SCREEN_NAME = {
        1 : '新浪微博',
        2 : '腾讯微博',
        3 : 'Twitter',//可以不认证
        4 : '网易微博',
        5 : '搜狐微博',
        6 : '嘀咕',//可以不认证
        7 : '饭否',//可以不认证
        8 : '做啥',//可以不认证
        9 : '人间',//可以不认证
        10 : '豆瓣'
    }

    Microblog.AUTH_METHOD = {
        2 : 1,
        3 : 1,
        10 : 1
    };

    Microblog.prototype.modify = function( nick, authtype ) {
        if ( authtype ) {
            if ( !( Microblog.AUTH_TYPE[this.type] & authtype ) )
                throw new Error( 'Invaild auth type.' );
            this.authtype = authtype;
        }
        this.nick = nick;
    };

    function Manager( arr ) {
        this.container = new Array();
        var i;
        for ( i in arr ) {
            try {
                this.container[arr[i][0]] = new Microblog( arr[i][1], arr[i][2], arr[i][3], arr[i][4] );
            } catch (ex) {
            }
        }
    }

    Manager.getAllMicroblogs = function() {
        var s = '';
        for ( var mid in Microblog.SCREEN_NAME ) {
            s += "<option value=\"" + mid + "\">" + Microblog.SCREEN_NAME[mid] + "</option>\n";
        }
        return s;
    }

    Manager.getAuthNames = function( authtype ) {
        var s = '';
        for ( var mid in Microblog.AUTH_NAME ) {
            if ( Microblog.AUTH_TYPE[authtype] & mid )
                s += "<option value=\"" + mid + "\">" + Microblog.AUTH_NAME[mid] + "</option>\n";
        }
        return s;
    }

    Manager.enable = function() {
        $('#account-td select, #account-td input').attr( 'disabled', false );
        $('#wait').hide();
    }

    Manager.disable = function() {
        $('#account-td select, #account-td input').attr( 'disabled', true );
        $('#wait').show();
    }

    Manager.getToken = function( type ) {
        Manager.disable();
        if (Microblog.AUTH_METHOD[type])
            window.open( 'javascript: document.write("请稍等...");', 'gettoken', 'width = 800, height = 600' );
        $.ajax({
            type: 'POST',
//            context: window,
            url : wm_plugin_url + '/get.php',
            data : {
                'type' : type
            },
            dataType : 'json',
            success : function( data ) {
                Manager.request_token = data.request_token.oauth_token;
                Manager.request_token_secret = data.request_token.oauth_token_secret;
                if (Microblog.AUTH_METHOD[type]) {
                    var popupWindow = window.open( data.url, 'gettoken', 'width = 800, height = 600' );
                    if (!popupWindow || popupWindow.closed || typeof popupWindow.closed=='undefined')
                        alert('您的浏览器已阻止弹出式窗口，部分微博认证需要允许弹出式窗口才能正常进行。');
                } else {
                    $('#container').hide();
                    $('#oauth-iframe iframe').attr('src', data.url);
                    $('#oauth-iframe').show();
                }
            },
            error : function() {
                alert('获得授权失败');
            },
            complete : function() {
                Manager.enable();
            }
        })
    }

    Manager.prototype.add = function( type, authtype, token, secret ) {
        Manager.disable();
        $.ajax({
            type : 'POST',
    //        context : this,
            url : wm_plugin_url + '/action.php',
            data : {
                'action' : 1,
                'type' : type,
                'authtype' : authtype,
                'token' : token,
                'secret' : secret,
                'request_token' : Manager.request_token,
                'request_token_secret' : Manager.request_token_secret
            },
            dataType : 'json',
            success : function( data ) {
                $('#account-info-div').hide();
                mb.container[data.mid] = new Microblog( data.type, data.nick, false, data.authtype );
                refreshAccountList();
            },
            error : function( XMLHttpRequest ) {
                alert( XMLHttpRequest.responseText );
            },
            complete : function() {
                Manager.enable();
            }
        });
    }

    Manager.prototype.remove = function( mid ) {
        Manager.disable();
        $.ajax({
            type : 'POST',
    //        context : this,
            url : wm_plugin_url + '/action.php',
            data : {
                'action' : 2,
                'mid' : mid
            },
            dataType : 'json',
            success : function( data ) {
                delete mb.container[data.mid];
                refreshAccountList();
            },
            error : function( XMLHttpRequest ) {
                alert( XMLHttpRequest.responseText );
            },
            complete : function() {
                Manager.enable();
            }
        });
    }

    Manager.prototype.modify = function( mid, authtype, token, secret ) {
        Manager.disable();
        $.ajax({
            type : 'POST',
    //        context : this,
            url : wm_plugin_url + '/action.php',
            data : {
                'action' : 3,
                'mid' : mid,
                'authtype' : authtype,
                'token' : token,
                'secret' : secret,
                'request_token' : Manager.request_token,
                'request_token_secret' : Manager.request_token_secret
            },
            dataType : 'json',
            success : function( data ) {
                $('#account-info-div').hide();
                mb.container[data.mid].modify( data.nick, data.authtype );
                refreshAccountList();
            },
            error : function( XMLHttpRequest ) {
                alert( XMLHttpRequest.responseText );
            },
            complete : function() {
                Manager.enable();
            }
        });
    }

    Manager.prototype.suspendToggle = function( mid ) {
        Manager.disable();
        $.ajax({
            type : 'POST',
    //        context : this,
            url : wm_plugin_url + '/action.php',
            data : {
                'action' : 4,
                'mid' : mid
            },
            dataType : 'json',
            success : function( data ) {
                mb.container[data.mid].suspend = data.suspend;
                refreshAccountList();
                $('#account-list option[value=' + data.mid + ']').attr( 'selected', 'selected' );
            },
            error : function( XMLHttpRequest ) {
                alert( XMLHttpRequest.responseText );
            },
            complete : function() {
                Manager.enable();
            }
        });
    }

    Manager.prototype.update = function( mid ) {
        Manager.disable();
        $.ajax({
            type : 'POST',
    //        context : this,
            url : wm_plugin_url + '/action.php',
            data : {
                'action' : 5,
                'mid' : mid
            },
            dataType : 'json',
            success : function( data ) {
                mb.container[data.mid].modify( data.nick )
                refreshAccountList();
                $('#account-list option[value=' + data.mid + ']').attr( 'selected', 'selected' );
            },
            error : function( XMLHttpRequest ) {
                alert( XMLHttpRequest.responseText );
            },
            complete : function() {
                Manager.enable();
            }
        });
    }

    Manager.prototype.getHTML = function() {
        var s = '', mid;
        for ( mid in mb.container ) {
            s += "<option value=\"" + mid + "\">" + mid + " " + Microblog.SCREEN_NAME[mb.container[mid].type] + " " + Microblog.AUTH_NAME[mb.container[mid].authtype] + " : " + mb.container[mid].nick + ( mb.container[mid].suspend == 1 ? " - " + "停用" : "" ) + "\n";
        }
        return s;
    }
    var mb = new Manager( wm_account_list );
    var isAdd = true, editMID;

    $('#microblog-type-list').html( Manager.getAllMicroblogs() );
    $('#auth-type').html( Manager.getAuthNames( 1 ) );
    refreshAccountList();
    Manager.enable();
    
    $('#ajax-add').click( function() {
        isAdd = true;
        $('#account-info-title').text( '添加微博帐号' );
        $('#td-add').show();
        $('#td-edit').hide();
        $('#microblog-type-list option:first, #auth-type option:first').attr( 'selected', 'selected' );
        $('#auth-type').html( Manager.getAuthNames( 1 ) );
        changeType();
        $('#account-info-div').show();
    });
    
    $('#ajax-remove').click( function() {
        if ( $('#account-list').val() == null || !confirm ( '您确定删除此帐号吗？' ) )
            return;
        mb.remove( $('#account-list').val() );
        refreshAccountList();
    });
    
    $('#ajax-suspend').click( function() {
        var mid = $('#account-list').val();
        if ( mid == null )
            return;
        mb.suspendToggle( mid );
    });
    
    $('#ajax-edit').click( function() {
        var mid = $('#account-list').val();
        if ( mid == null )
            return;
        isAdd = false;
        editMID = mid;
        $('#account-info-title').text( '编辑微博帐号' );
        $('#td-add').hide();
        var thisMB = mb.container[mid];
        $('#td-edit').show().text( Microblog.SCREEN_NAME[thisMB.type] );
        $('#auth-type').html( Manager.getAuthNames( thisMB.type ) );
        $('#auth-type option[value=' + thisMB.authtype + ']').attr( 'selected', 'selected' );
        changeType();
        $('#account-info-div').show();
    });

    $('#ajax-update').click( function() {
        var mid = $('#account-list').val();
        if ( mid == null )
            return;
        mb.update( mid );
    })
    
    $('#microblog-type-list').change( function() {
        changeAccountList();
    });
    
    $('#auth-type').change( function() {
        changeType();
    });
    
    $('#oauth-get-token').click( function() {
        var type;
        if ( isAdd ) {
            type = $('#microblog-type-list').val();
        } else {
            type = mb.container[editMID].type;
        }
        Manager.getToken( type );
    });

    $('#token-or-username, #basic-password').keydown( function(event) {
        if (event.keyCode == 13) 
            $('#ajax-submit').click();
    });
    
    $('#ajax-submit').click( function() {
        if ( !$('#token-or-username').val() || $('#auth-type').val() & 12 && !$('#basic-password').val() )
            return;
        if ( isAdd ) {
            mb.add( $('#microblog-type-list').val(), $('#auth-type').val(), $('#token-or-username').val(), $('#basic-password').val() );
        } else
            mb.modify( editMID, $('#auth-type').val(), $('#token-or-username').val(), $('#basic-password').val() );
    });
    
    $('#ajax-cancel').click( function() {
        $('#account-info-div').hide();
    });

    $('#oauth-back').click(function() {
        hideOAuthIframe();
    });
    
    function changeAccountList() {
        $('#auth-type').html( Manager.getAuthNames( $('#microblog-type-list').val() ) );
        changeType();
    }
    
    function changeType() {
        $('#token-or-username, #basic-password').val( '' );
        var authtype = $('#auth-type').children('option:selected').val()
        switch ( Number( authtype ) ) {
            case 1 :
                $('#th-title label').text( '用户名' );
                $('#oauth-get-token').hide();
                $('#tr-password').val( '' ).hide();
                $('#oauth-msg-popup').hide();
                break;
            case 2 :
                $('#th-title label').text( 'OAuth 密匙' );
                $('#oauth-get-token').show();
                $('#tr-password').val( '' ).hide();
                $('#oauth-msg-popup').show();
                break;
            case 8 :
                $('#th-title label').text( '用户名' );
                $('#oauth-get-token').hide();
                $('#tr-password').val( '' ).show();
                $('#oauth-msg-popup').hide();
                break;
            default :
        }
    }

    function refreshAccountList() {
        jQuery('#account-list').html( mb.getHTML() );
    }
});

function hideOAuthIframe() {
    jQuery('#oauth-iframe iframe').attr('src', 'about:blank');
    jQuery('#oauth-iframe').hide();
    jQuery('#container').show();
}