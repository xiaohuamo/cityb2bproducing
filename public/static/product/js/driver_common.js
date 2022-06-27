
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
    webUrl: 'https://m.cityb2b.com/',//web访问地址
    dWebUrl: 'https://d.cityb2b.com/',//web访问地址
    driverWebUrl: 'https://d.cityb2b.com/',//web访问地址
    // webUrl: 'http://192.168.50.105/',//web访问地址
    // dWebUrl: 'http://127.0.0.2/',//web访问地址
    // driverWebUrl: 'http://127.0.0.3/',//web访问地址
    // driverWebUrl: 'http://192.168.50.105/',//web访问地址
}
axios.defaults.baseURL = common.driverWebUrl;
axios.defaults.headers.common['Authorization'] = localStorage.getItem("token");
function getData(url,data,callback){
    axios({
        method: "POST",
        url:url,
        data:JSON.stringify(data), //  字符串格式
        headers:{
            'Content-Type':'application/json',
        },
    }).then(resp=> {
        // console.log('sssss---',resp.data);
        if(resp.data.status == 144){
            //清空本地存储信息，并跳转到登录页面
            localStorage.clear();
            window.location.href = common.driverWebUrl+'driver/login'
        }else{
            callback(resp.data)
        }
    }).catch(error=>{
        alert(error)
    });
}

function getFormData(url,data,callback){
    axios({
        method: "POST",
        url:url,
        data:data, //  字符串格式
        headers:{
            'Content-Type':'application/form-data',
        },
    }).then(resp=> {
        // console.log('sssss---',resp.data);
        if(resp.data.status == 144){
            //清空本地存储信息，并跳转到登录页面
            localStorage.clear();
            window.location.href = common.driverWebUrl+'driver/login'
        }else{
            callback(resp.data)
        }
    }).catch(error=>{
        alert(error)
    });
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
