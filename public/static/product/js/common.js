
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

let common = {
    // apiUrl: 'http://product.cooltechsolution.com/',//api接口地址
    // webUrl: 'http://product.cooltechsolution.com/',//web访问地址
    apiUrl: 'http://192.168.50.105/',//api接口地址
    webUrl: 'http://192.168.50.105/',//web访问地址
}

function getData(url,data,callback){
    $.ajax({
        type: "POST",
        headers:{
            'Content-Type':'application/json',
        },
        url:common.apiUrl+url,
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
