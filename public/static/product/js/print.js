//print_type=-1 打印类型 -1打印生产类型 1-Fit print all 2-fit print 3-print order 4-blank label
function preview(parsedOrders,totalCopy=1,goods,goodsTwoCate,userName,businessName,print_type=-1) {
    print_type = parseInt(print_type)
    // if(parsedOrders.first_name || parsedOrders.last_name){
    //     parsedOrders.nickname_print = parsedOrders.nickname+'('+parsedOrders.last_name+' '+parsedOrders.first_name+')'
    // }
    init(parsedOrders,totalCopy,goods,goodsTwoCate,userName,businessName,print_type);
    LODOP.PREVIEW();
}
//type=1 默认打印产品信息 type=2 打印物流信息
function print(parsedOrders,totalCopy=1,goods,goodsTwoCate,userName,businessName,print_type=-1) {
    print_type = parseInt(print_type)
    init(parsedOrders,totalCopy,goods,goodsTwoCate,userName,businessName,print_type);
    LODOP.PRINT();
}

var LODOP;
var PRINT_MODE_SINGLE_LABEL_PER_PAGE = 'SINGLE_LABEL_PER_PAGE';
var PRINT_MODE_THREE_LABEL_PER_PAGE = 'THREE_LABEL_PER_PAGE';
var DEFAULT_PRINT_MODE = PRINT_MODE_SINGLE_LABEL_PER_PAGE;

function init(order,totalCopy,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP = getLodop();
    LODOP.PRINT_INIT("CityB2B-打印机预览");
    // LODOP.SET_PRINT_PAGESIZE(1, '3.6 in', '7 in', "");
    if(print_type == -1){
        LODOP.SET_PRINT_PAGESIZE(1, '70mm', '50mm', "");
    }else{
        LODOP.SET_PRINT_PAGESIZE(1, '100mm', '100mm', "");
    }

    if (DEFAULT_PRINT_MODE === PRINT_MODE_SINGLE_LABEL_PER_PAGE) {
        generateOrderPrint(order, totalCopy,goods,goodsTwoCate,businessName,userName,print_type);
    } else if (DEFAULT_PRINT_MODE === PRINT_MODE_THREE_LABEL_PER_PAGE) {
        generateOrderPrint2(order, totalCopy,goods,goodsTwoCate,businessName,userName,print_type);
    }
}

//single label per page
function generateOrderPrint(order,copy,goods,goodsTwoCate,businessName,userName,print_type) {
    switch (print_type){
        case -1:
            for (var i = 0; i < copy; i++) {
                order.boxLabel = i + 1 + " of " + copy;
                addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
            }
            break;
        //默认打印一张带标签的
        case 0:
            //如果当前标签id是已打印过的标签id，则只打印当前输入的标签id
            //如果未打印过，则需要打印新的id
            var mix_group_data = order.mix_group_data
            var newcopysortid = parseInt(order.current_boxesNumberSortId);
            order.boxLabel = newcopysortid + " of " + copy;
            if(newcopysortid!=order.mix_box_sort_id){
                order.mix_group_data=[];
            }else{
                order.mix_group_data=mix_group_data
            }
            addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
            return;
            break;
        //print fit all 打印该产品的订单明细
        case 1:
            break;
        //print fit 打印该订单明细的当前所有标签
        case 2:
            //标记开始打印的位置，当前产品已打印的个数
            var mix_group_data = order.mix_group_data
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
                for (var i = pls_start; i < pls_end; i++) {
                    var newcopysortid = parseInt(order.print_label_sorts_arr[i])
                    if(newcopysortid<=parseInt(copy)){
                        order.boxLabel = newcopysortid + " of " + copy;
                        if(newcopysortid!=order.mix_box_sort_id){
                            order.mix_group_data=[];
                        }else{
                            order.mix_group_data=mix_group_data
                        }
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
                        if(newcopysortid!=order.mix_box_sort_id){
                            order.mix_group_data=[];
                        }else{
                            order.mix_group_data=mix_group_data
                        }
                        addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
                    }
                }
            }
            return;
            break;
        //print order 打印该订单的所有标签
        case 3:
            for (var i = 0; i < copy; i++) {
                order.boxLabel = i + 1 + " of " + copy;
                addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
            }
            return;
            break;
        //blank label 打印一张空白标签
        case 4:
            order.boxLabel = '__' + " of " + '__';
            var mix_group_data = order.mix_group_data
            var newcopysortid = parseInt(order.current_boxesNumberSortId);
            if(newcopysortid!=order.mix_box_sort_id){
                order.mix_group_data=[];
            }else{
                order.mix_group_data=mix_group_data
            }
            addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type);
            return;
            break;

    }
}
function addOnePage(order,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);
    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body style='font-size:12px' leftmargin=0 topmargin=0>"+labelTemplate(order,goods,goodsTwoCate,businessName,userName,print_type)+"</body>");
}

//max 3 label per page
function generateOrderPrint2(order, copy,goods,goodsTwoCate,businessName,userName,print_type) {
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);

    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

    var template = '';
    template += labelTemplate_ThreeLabelPerPage_Main(order, "1 of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
    if (copy >1) {
        template += "<div style='height:70px'></div>";
        template += labelTemplate_ThreeLabelPerPage_Sub(order, "2 of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
    }

    /*	if (copy > 2) {
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, "3 of " + copy);
        } */

    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");

    if (copy > 2) {
        for (var i = 1; i <= copy/2-1; i++) {
            LODOP.NewPage();
            //QR CODE
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
            // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

            var template = '';
            template += labelTemplate_ThreeLabelPerPage_Main(order,  i*2+1 + " of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*2+2+" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);

            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }

        if (copy%2 == 1) {
            LODOP.NewPage();
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);

            template = ''
            template += labelTemplate_ThreeLabelPerPage_Main(order, copy +" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
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
                template += labelTemplate_ThreeLabelPerPage_Main(order,i+2 + " of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            } else {
                template += "<div style='height:360px'></div>";
                template += labelTemplate_ThreeLabelPerPage_Sub(order, i+2 +" of " + copy,goods,goodsTwoCate,businessName,userName,print_type);
            }
            /*template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*3+3 +" of " + copy); */
            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }
    }
}
function labelTemplate(order,goods,goodsTwoCate,businessName,userName,print_type) {
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
    if(order.unit_en==null){
        order.unit_en=''
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
                alert(goods.menu_en_name);
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
        let pon_style = 'style="padding-top:0px; padding-left:5px;"'
        let on_style = 'style="font-size:29px;"';
        if(order.name.length>16){
            order.name = order.name.slice(0,16);
            pon_style = 'style="padding-top:5px; padding-left:5px;"'
            on_style = 'style="font-size:28px;"'
        }
        // html+='<p '+pon_style+'>'+order.customer_delivery_option+'&nbsp;<strong '+on_style+'>'+order.name+'</strong></p>';
        html+='<p '+pon_style+'><strong '+on_style+'>'+order.name+'</strong></p>';

        html+='<table style=" padding-left:5px;padding-right:5px; width: 100%;font-size:28px;font-weight:bolder" cellspacing="0" cellpadding="0">';
        html+='<tr style="">';
        html+=	'<td style=" height:36px;;width:25%;font-size:16px ;border-width: 1px 1px 1px 1px;border-style:solid;text-align: left; ">&nbsp;&nbsp;'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</td>';
        html+=	'<td style=" height:36px;width:75%;border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;"><span style="font-size:20px">'+name+'-'+truck_name+'-'+plate_number+'</span></td>';
        html+='</tr>';
        html+='<tr style="height:36px;">';
        html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: left;font-size:32px;" >&nbsp;&nbsp;'+order.logistic_sequence_No+'</td>';
        if(order.boxLabel.length>=8){
            html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;" ><span style="font-size:16px">DROPNO&nbsp;</span>'+order.logistic_stop_No+'&nbsp;&nbsp;<span style="font-size:12px">BOX </span> '+order.boxLabel+'&nbsp;&nbsp;</td>';
        }else{
            html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;" ><span style="font-size:16px">DROPNO&nbsp;</span>'+order.logistic_stop_No+'&nbsp;&nbsp;<span style="font-size:16px">BOX </span> '+order.boxLabel+'&nbsp;&nbsp;</td>';
        }

        html+='</tr>';
        html+='</table>';
        html+='	<label>CustId:'+order.userId+'</label>';
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
            let one_menu_len = 0;//记录商品的总长度，大于一定长度，字体缩小
            if(order.mix_group_data.length == 1){
                one_menu_len += order.mix_group_data[0].menu_en_name.length+order.mix_group_data[0].guige_name.length+order.mix_group_data[0].new_customer_buying_quantity.length+order.unit_en.length
            }else{
                one_menu_len += order.menu_en_name.length+order.guige_name.length+order.new_customer_buying_quantity.length+order.unit_en.length
            }
            if(one_menu_len<40){
                product_style = ' style="font-size:16px;"';
            }
        }
        if(order.mix_group_data != undefined && order.mix_group_data.length > 0){
            if(order.mix_group_data.length > 3){
                html+='<div style="display: flex;width: 100%;"><label>MIX:</label>\n';
                html+='<div style="margin-left: 5px; word-break:break-all;"'+product_style+'>';
                let menu_len = 0;//记录商品总的字符串长度
                for(var i=0;i<order.mix_group_data.length;i++){
                    menu_len += order.mix_group_data[i].menu_en_name.length+1+order.mix_group_data[i].guige_name.length+1+order.mix_group_data[i].new_customer_buying_quantity.length+order.unit_en.length+1
                    if(menu_len<=155){
                        html+=order.mix_group_data[i].menu_en_name+'&nbsp;'+order.mix_group_data[i].guige_name+'&nbsp;'+order.mix_group_data[i].new_customer_buying_quantity+order.unit_en+'&nbsp;|&nbsp;';
                    }
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
        html+='<div>';
        //html+='	<span >'+order.logistic_suppliers_info+'</span>';
        html+='	<div style="border: 0px solid black; padding: 0 5px;"><span>DNL FOOD   license  NO:P01417</span></div>';
        html+='<div style="border: 0px solid black; padding: 0 5px 2px;">	<span >TEL:93988222  0450599336 </span></div>';
        html+='<div style="border: 0px solid black; padding: 0 5px;">	<span >ADD:30 Blaxland Ave, Thomastown VIC 3074</span></div>';
        html+='</div>';

        // html+='<p>offline|Delivery  CustId:'+order.userId+ '<br>CustName:<strong  style=\"width: 100%;font-size:25px;font-weight:bolder\" >'+order.nickname+'</strong></p>';
        // html+='<table style="width: 100%;font-size:30px;font-weight:bolder" cellspacing="0" cellpadding="0">';
        // html+='<tr>';
        // html+=	'<td style="font-size:18px ;border-width: 2px 1px 1px 2px;border-style:solid;text-align: left; ">&nbsp;&nbsp;'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</td>';
        // html+=	'<td style=" border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;"><span style="font-size:18px">Drop No:&nbsp;&nbsp;</span>'+order.logistic_stop_No+'&nbsp;&nbsp;</td>';
        // html+='</tr>';
        // html+='<tr>';
        // html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: left;" >&nbsp;&nbsp;'+order.logistic_sequence_No+'</td>';
        // html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;" >&nbsp;<span style="font-size:18px">Box </span> '+order.boxLabel+'&nbsp;&nbsp;</td>';
        //
        // html+='</tr>';
        // html+='</table>';
        //
        // html+='<br>';
        // html+='	<label>Deliver Address:</label>';
        // html+='<br>';
        // html+='<div style="border: 3px solid black; padding: 5px">';
        // html+='	<span style="font-weight: bolder;">'+order.address + '</span>';
        //
        // html+='</div>';
        // html+='<div>';
        // html+='	<label>Note:</label>  ';
        // html+='	<small>'+order.message_to_business+'</small>';
        // html+='</div>';
        // html+='<br><br>';
        // html+='<hr><br>';
        //
        // html+='<div>';
        // html+='	<label>Order ID:</label>';
        // html+='	<span style="float:right;">'+order.orderId+'</span>';
        // html+='</div>';
        // html+='<div>';
        // html+='	<label>Name:</label>';
        // html+='	<span style="float:right;">'+order.first_name+' '+order.last_name+'</span>';
        // html+='</div>';
        // html+='<div>';
        // html+='	<label>Phone:</label>';
        // html+='	<span style="float:right;">'+order.phone+'</span>';
        // html+='</div>';
        //
        // html+='<div>';
        // html+='	<label>Truck No:</label>';
        // html+='	<span style="float:right;">'+order.logistic_truck_No+'</span>';
        // html+='</div>';
        // html+='<div>';
        // html+='	<label>Stop No:</label>';
        // html+='	<span style="float:right;">'+order.logistic_stop_No+'</span>';
        // html+='</div>';
        // html+='<br><hr><br>';
        // html+='<div>';
        // html+='	<label>Suppliers Count:</label>';
        // html+='	<span style="float:right;">'+order.logistic_suppliers_count+'</span>';
        // html+='</div>';
        // html+='<label>Suppliers Name:</label>';
        // html+='<div style="border: 1px solid black; padding: 5px">';
        // html+='	<span >'+order.logistic_suppliers_info+'</span>';
        // html+='</div>';
    }
    return html;
}

function labelTemplate_ThreeLabelPerPage_Main(order, boxLabel,goods,goodsTwoCate,businessName,userName,print_type) {

}

function labelTemplate_ThreeLabelPerPage_Sub(order, boxLabel,goods,goodsTwoCate,businessName,userName,print_type) {

}
