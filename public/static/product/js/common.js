
function isButtonLoading(isLoading, button) {
    if(isLoading) {
        button.prop('disabled', true);
        button.find('.spinner-border').show();
        button.find('.arrow_right').hide();
    } else {
        button.prop('disabled', false);
        button.find('.spinner-border').hide();
        button.find('.arrow_right').show();
    }
}

const common = {
    // webUrl: 'https://m.cityb2b.com/',//web访问地址
    // dWebUrl: 'https://d.cityb2b.com/',//web访问地址
    // driverWebUrl: 'https://driver.cityb2b.com/',//web访问地址
    webUrl: 'http://192.168.50.105/',//web访问地址
    dWebUrl: 'http://127.0.0.2/',//web访问地址
    driverWebUrl: 'http://127.0.0.3/',//web访问地址
}

function getData(url,data,callback){
    $.ajax({
        type: "POST",
        headers:{
            'Content-Type':'application/json',
        },
        url:url,
        // timeout:10000,
        data:JSON.stringify(data), //  字符串格式
        complete(xhr){
            // console.log(xhr);
            callback(xhr.responseJSON)
        },
        error:function(xhr,msg){
            alert(msg)
        }
    })
}

//加密方法。没有过滤首尾空格，即没有trim.
//加密可以加密N次，对应解密N次就可以获取明文
function encodeBase64(mingwen,times){
    var code="";
    var num=1;
    if(typeof times=='undefined'||times==null||times==""){
        num=1;
    }else{
        var vt=times+"";
        num=parseInt(vt);
    }
    if(typeof mingwen=='undefined'||mingwen==null||mingwen==""){
    }else{
        $.base64.utf8encode = true;
        code=mingwen;
        for(var i=0;i<num;i++){
            code=$.base64.btoa(code);
        }
    }
    return code;
}

//解密方法。没有过滤首尾空格，即没有trim
//加密可以加密N次，对应解密N次就可以获取明文
function decodeBase64(mi,times){
    var mingwen="";
    var num=1;
    if(typeof times=='undefined'||times==null||times==""){
        num=1;
    }else{
        var vt=times+"";
        num=parseInt(vt);
    }
    if(typeof mi=='undefined'||mi==null||mi==""){
    }else{
        $.base64.utf8encode = true;
        mingwen=mi;
        for(var i=0;i<num;i++){
            mingwen=$.base64.atob(mingwen);
        }
    }
    return mingwen;
}
