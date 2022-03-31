
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
    webUrl: 'https://m.cityb2b.com/',//web访问地址
    dWebUrl: 'https://d.cityb2b.com/',//web访问地址
    // webUrl: 'http://192.168.50.105/',//web访问地址
    // dWebUrl: 'http://127.0.0.1/',//web访问地址
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
