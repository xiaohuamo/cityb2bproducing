//print_type=-1 打印类型 -1打印生产类型 1-Fit print all 2-fit print 3-print order 4-blank label
function previewAll(parsedOrders,goods,goodsTwoCate,userName,businessName,print_type=-1) {
    initAll(parsedOrders,goods,goodsTwoCate,userName,businessName,print_type);
    LODOP.PREVIEW();
}
//type=1 默认打印产品信息 type=2 打印物流信息
function printAll(parsedOrders,goods,goodsTwoCate,userName,businessName,print_type=-1) {
    initAll(parsedOrders,goods,goodsTwoCate,userName,businessName,print_type);
    LODOP.PRINT();
}

var LODOP;
var PRINT_MODE_SINGLE_LABEL_PER_PAGE = 'SINGLE_LABEL_PER_PAGE';
var PRINT_MODE_THREE_LABEL_PER_PAGE = 'THREE_LABEL_PER_PAGE';
var DEFAULT_PRINT_MODE = PRINT_MODE_SINGLE_LABEL_PER_PAGE;

function initAll(order,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP = getLodop();
    LODOP.PRINT_INIT("CityB2B-打印机预览");
    // LODOP.SET_PRINT_PAGESIZE(1, '3.6 in', '7 in', "");
    if(print_type == -1){
        LODOP.SET_PRINT_PAGESIZE(1, '70mm', '50mm', "");
    }else{
        LODOP.SET_PRINT_PAGESIZE(1, '100mm', '100mm', "");
    }
    if (DEFAULT_PRINT_MODE === PRINT_MODE_SINGLE_LABEL_PER_PAGE) {
        generateOrderPrintAll(order, goods,goodsTwoCate,businessName,userName,print_type);
    } else if (DEFAULT_PRINT_MODE === PRINT_MODE_THREE_LABEL_PER_PAGE) {
        generateOrderPrint2All(order, goods,goodsTwoCate,businessName,userName,print_type);
    }
}

//single label per page
function generateOrderPrintAll(order,goods,goodsTwoCate,businessName,userName,print_type) {
    switch (print_type){
        //print fit all 打印该产品的订单明细
        case 1:
            $.each(order,function(i,item){
                printOne(item,goods,goodsTwoCate,businessName,userName,print_type)
                // var copy = order[i].boxesNumber
                // for (var j = 0; j < order[i].boxes; j++) {
                //     var newcopysortid = parseInt(order[i].boxesNumberSortId)+j
                //     if(newcopysortid<=parseInt(copy)){
                //         order[i].boxLabel = newcopysortid + " of " + copy;
                //         addOnePageAll(order[i],goods,goodsTwoCate,businessName,userName,print_type);
                //     }
                // }
            })
            break;
    }
}
function printOne(order,goods,goodsTwoCate,businessName,userName,print_type){
        if((typeof order.current_boxesNumberSortId=='string')&&order.current_boxesNumberSortId.constructor==String){
            var index = $.inArray(order.current_boxesNumberSortId,order.print_label_sorts_arr);
        }else{
            var index = $.inArray(order.current_boxesNumberSortId.toString(),order.print_label_sorts_arr);
        }
        if(index == -1){
            //新的标签号不在已存的标签序号里，说明是新的打印号码，打印剩余的即可
            var end = order.boxes-order.print_label_sorts_length;
        }else{
            //如果当前标签号已打印过，则打印当前号码之后的所有号码，直到当前产品全部打印完成
            if(index == order.print_label_sorts_length-1){
                var pls_start = 0;
                var pls_end = order.print_label_sorts_length;
            }else{
                var pls_start = index;
                var pls_end = order.print_label_sorts_length;
            }
            var copy=parseInt(order.boxesNumber)
            for (var i = pls_start; i < pls_end; i++) {
                var newcopysortid = parseInt(order.print_label_sorts_arr[i])
                if(newcopysortid<=copy){
                    order.boxLabel = newcopysortid + " of " + copy;
                    addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
                }
            }
            //判断是否有剩余的标签要打，有则打印新的
            if(order.print_label_sorts_length<order.boxes){
                var end = order.boxes-order.print_label_sorts_length;
            }else{
                var end = 0;
            }
        }
        if(end > 0){
            for (var i = 0; i < end; i++) {
                var newcopysortid = parseInt(order.old_boxesNumberSortId)+i
                if(newcopysortid<=parseInt(copy)){
                    order.boxLabel = newcopysortid + " of " + copy;
                    addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
                }
            }
        }
}
function addOnePageAll(order,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);
    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body style='font-size:12px' leftmargin=0 topmargin=0>"+labelTemplateAll(order,goods,goodsTwoCate,businessName,userName,print_type)+"</body>");
}

//max 3 label per page
function generateOrderPrint2All(order, copy,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);

    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

    var template = '';
    template += labelTemplate_ThreeLabelPerPage_MainAll(order, "1 of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
    if (copy >1) {
        template += "<div style='height:70px'></div>";
        template += labelTemplate_ThreeLabelPerPage_SubAll(order, "2 of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
    }

    /*	if (copy > 2) {
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_SubAll(order, "3 of " + copy);
        } */

    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");

    if (copy > 2) {
        for (var i = 1; i <= copy/2-1; i++) {
            console.log('sssss---')
            LODOP.NewPage();
            //QR CODE
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
            // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

            var template = '';
            template += labelTemplate_ThreeLabelPerPage_MainAll(order,  i*2+1 + " of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_SubAll(order, i*2+2+" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);

            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }

        if (copy%2 == 1) {
            LODOP.NewPage();
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);

            template = ''
            template += labelTemplate_ThreeLabelPerPage_MainAll(order, copy +" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }
    }
    if (copy > 12) {
        for (var i = 1; i < copy-1; i++) {
            if ((i+2)%2 == 1) { //如果是3，5，7，9 copy 则新建一个打印页，首先打带二维码的 main页面。
                LODOP.NewPage();
                var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
                LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
                // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

                var template = '';
                template += labelTemplate_ThreeLabelPerPage_MainAll(order,i+2 + " of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            } else {
                template += "<div style='height:360px'></div>";
                template += labelTemplate_ThreeLabelPerPage_SubAll(order, i+2 +" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            }
            /*template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_SubAll(order, i*3+3 +" of " + copy); */
            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }
    }
}
function labelTemplateAll(order,goods,goodsTwoCate,businessName,userName,print_type) {
    var html = '';
    //司机信息
    var name = ''
    var truck_name = ''
    var plate_number = ''
    if(!jQuery.isEmptyObject(order.truck_info)){
        name = order.truck_info.name
        truck_name = order.truck_info.truck_name
        plate_number = order.truck_info.plate_number
    }
    if(print_type == -1){
        if(order.nickname.length>20 || goods.menu_en_name.length>20 || (goods.is_has_two_cate == 1 && goodsTwoCate.guige_name.length > 20)){
            if(order.nickname.length>25){
                order.nickname = order.nickname.substr(0,25)
            }
            html += '<div style="font-size: 15px;padding: 5px 15px;">\n' +
                '        <div style="margin: 4px 0;">'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</div>\n'
            if(!jQuery.isEmptyObject(order.truck_info)){
                html += '        <div style="margin: 4px 0;"><span>'+order.truck_info.name+'-'+order.truck_info.truck_name+'-'+order.truck_info.plate_number+'</span></div>\n'
            }
            html += '        <p style="margin: 4px 0;">'+order.nickname+'</p>\n' +
                '        <div style="position: absolute;top: 8px;right: 15px;font-weight: bolder;font-size: 25px;">'+order.logistic_sequence_No+'</div>\n'
            if(goods.is_has_two_cate == 1){
                html += '        <div style="margin: 4px 0;"><span>'+goodsTwoCate.guige_name+'</span><span style="display: inline-block;width: 60px;margin-left: 16px;">'+order.new_customer_buying_quantity+'kg</span></div>\n';
            }else{
                html += '        <div style="margin: 4px 0;"><span>'+goods.menu_en_name+'</span><span style="display: inline-block;width: 60px;margin-left: 16px;">'+order.new_customer_buying_quantity+'kg</span></div>\n'
            }
            html += '        <div style="display: flex;"><span style="display: inline-block;width: 100px">Box '+order.boxLabel+'</span><span style="flex: 1;text-align: right;font-weight: normal;">'+userName+'</span></div>\n' +
                '    </div>';
            return html;
        } else {
            html += '<div style="font-size: 18px;padding: 5px 15px;">\n' +
                '        <div style="margin: 8px 0;">'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</div>\n'
            if(!jQuery.isEmptyObject(order.truck_info)){
                html += '        <div style="margin: 8px 0;"><span>'+order.truck_info.name+'-'+order.truck_info.truck_name+'-'+order.truck_info.plate_number+'</span></div>\n'
            }
            html += '        <div style="margin: 8px 0;">'+order.nickname+'</div>\n' +
                '        <div style="position: absolute;top: 10px;right: 15px;font-weight: bolder;font-size: 25px;">'+order.logistic_sequence_No+'</div>\n'
            if(goods.is_has_two_cate == 1){
                html += '        <div style="margin: 8px 0;"><span>'+goodsTwoCate.guige_name+'</span><span style="display: inline-block;width: 60px;margin-left: 16px;">'+order.new_customer_buying_quantity+'kg</span></div>\n';
            }else{
                html += '        <div style="margin: 8px 0;"><span>'+goods.menu_en_name+'</span><span style="display: inline-block;width: 60px;margin-left: 16px;">'+order.new_customer_buying_quantity+'kg</span></div>\n';
            }
            html += '        <div style="display: flex;"><span style="display: inline-block;width: 100px">Box '+order.boxLabel+'</span><span style="flex: 1;text-align: right;font-weight: normal;">'+userName+'</span></div>\n' +
                '    </div>';
        }
    }else{
        html+='<p style="padding-top:10px; padding-left:5px;">'+order.subtitle+'</p>';

        html+='<table style=" padding-left:5px;padding-right:5px; width: 100%;font-size:28px;font-weight:bolder" cellspacing="0" cellpadding="0">';
        html+='<tr style="">';
        html+=	'<td style=" height:36px;;width:25%;font-size:16px ;border-width: 1px 1px 1px 1px;border-style:solid;text-align: left; ">&nbsp;&nbsp;'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</td>';
        html+=	'<td style=" height:36px;width:75%;border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;"><span style="font-size:16px">TRUCK&nbsp;'+truck_name+'</span></td>';
        html+='</tr>';
        html+='<tr style="height:36px;">';
        html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: left;" >&nbsp;&nbsp;'+order.logistic_sequence_No+'</td>';
        html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;" ><span style="font-size:16px">DROPNO&nbsp;</span>'+order.logistic_stop_No+'&nbsp;&nbsp;<span style="font-size:16px">BOX </span> '+order.boxLabel+'&nbsp;&nbsp;</td>';

        html+='</tr>';
        html+='</table>';

        html+='<br>';
        html+='	<label>Deliver Address:</label>';
        html+='<br>';
        html+='<div style="border: 2px solid black; padding: 5px">';
        html+='	<span style="font-weight: bolder;">'+order.address + '</span>';

        html+='</div>';
        html+='<div>';
        html+='	<label>Note:</label>  ';
        html+='	<small>'+order.message_to_business+'</small>';
        html+='</div>';
        html+='<br>';
        html+='<hr>';
        //判断产品是否只有一个，若只有一个，则字体放大
        var product_style = '';
        if(order.mix_group_data.length <= 1){
            product_style = ' style="font-size:16px;"';
        }
        if(order.mix_group_data != undefined && order.mix_group_data.length > 0){
            if(order.mix_group_data.length > 3){
                html+='<div style="display: flex;"><label>MIX:</label>\n';
                html+='<div style="margin-left: 5px;">';
                for(var i=0;i<order.mix_group_data.length;i++){
                    html+='<span'+product_style+'>'+order.mix_group_data[i].menu_en_name+'&nbsp;'+order.mix_group_data[i].guige_name+'&nbsp;&nbsp;'+order.mix_group_data[i].new_customer_buying_quantity+order.unit_en+'</span>&nbsp;<span style="font-weight: bold;">|</span>&nbsp;';
                }
                html+='</div></div><hr>';
            }else{
                html+='<div style="display: flex;"><label>MIX:</label>\n';
                html+='<div style="margin-left: 5px;">';
                for(var i=0;i<order.mix_group_data.length;i++){
                    html+='<div'+product_style+'>'+order.mix_group_data[i].menu_id+'&nbsp;&nbsp;'+order.mix_group_data[i].menu_en_name+'&nbsp;'+order.mix_group_data[i].guige_name+'&nbsp;&nbsp;'+order.mix_group_data[i].new_customer_buying_quantity+order.unit_en+'</div>';
                }
                html+='</div></div><hr>';
            }
        }else{
            html+='<br><div><span '+product_style+'>'+order.menu_id+'&nbsp;&nbsp;'+order.menu_en_name+'&nbsp;'+order.guige_name+'&nbsp;&nbsp;'+order.new_customer_buying_quantity+order.unit_en+'</span></div><hr>';
        }
        html+='<div>';
        html+='	<label>Order ID:</label>';
        html+='	<span >'+order.orderId+'</span>&nbsp;&nbsp;';

        html+='	<label>Phone:</label>';
        html+='	<span style="float:right;">'+order.phone+'</span>';
        html+='</div>';
        html+='<hr>';
        // html+='<label>Suppliers Name:</label>';
        html+='<div style="border: 0px solid black; padding: 2px">';
        //html+='	<span >'+order.logistic_suppliers_info+'</span>';
        html+='	<span>DNL FOOD   license  NO:P01417</span></div>';
        html+='<div style="border: 0px solid black; padding: 0 5px 2px;">	<span >TEL:93988222  0450599336 </span></div>';
        html+='<div style="border: 0px solid black; padding: 0 5px;">	<span >ADD:30 Blaxland Ave, Thomastown VIC 3074</span></div>';
        html+='</div>';
    }
    return html;
}

function labelTemplate_ThreeLabelPerPage_MainAll(order, boxLabel,goods,goodsTwoCate,businessName,userName,print_type) {

}

function labelTemplate_ThreeLabelPerPage_SubAll(order, boxLabel,goods,goodsTwoCate,businessName,userName,print_type) {

}
