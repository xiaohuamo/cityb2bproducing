//type=1 默认打印产品信息 type=2 打印物流信息
function preview(parsedOrders,totalCopy=1,goods,goodsTwoCate,userName,businessName,type=1,copysort=1,is_print_all=false,is_blank_lable=false) {
    // if(parsedOrders.first_name || parsedOrders.last_name){
    //     parsedOrders.nickname_print = parsedOrders.nickname+'('+parsedOrders.last_name+' '+parsedOrders.first_name+')'
    // }
    init(parsedOrders,totalCopy,goods,goodsTwoCate,userName,businessName,type,copysort,is_print_all,is_blank_lable);
    LODOP.PREVIEW();
}
//type=1 默认打印产品信息 type=2 打印物流信息
function print(parsedOrders,totalCopy=1,goods,goodsTwoCate,userName,businessName,type=1,copysort=1,is_print_all=false,is_blank_lable=false) {
    init(parsedOrders,totalCopy,goods,goodsTwoCate,userName,businessName,type,copysort,is_print_all,is_blank_lable);
    LODOP.PRINT();
}

var LODOP;
var PRINT_MODE_SINGLE_LABEL_PER_PAGE = 'SINGLE_LABEL_PER_PAGE';
var PRINT_MODE_THREE_LABEL_PER_PAGE = 'THREE_LABEL_PER_PAGE';
var DEFAULT_PRINT_MODE = PRINT_MODE_SINGLE_LABEL_PER_PAGE;

function init(order,totalCopy,goods,goodsTwoCate,businessName,userName,type,copysort,is_print_all,is_blank_lable) {
    LODOP = getLodop();
    LODOP.PRINT_INIT("CityB2B-打印机预览");
    // LODOP.SET_PRINT_PAGESIZE(1, '3.6 in', '7 in', "");
    if(type == 1){
        LODOP.SET_PRINT_PAGESIZE(1, '70mm', '50mm', "");
    }else{
        LODOP.SET_PRINT_PAGESIZE(1, '100mm', '100mm', "");
    }
    if (DEFAULT_PRINT_MODE === PRINT_MODE_SINGLE_LABEL_PER_PAGE) {
        generateOrderPrint(order, totalCopy,goods,goodsTwoCate,businessName,userName,type,copysort,is_print_all,is_blank_lable);
    } else if (DEFAULT_PRINT_MODE === PRINT_MODE_THREE_LABEL_PER_PAGE) {
        generateOrderPrint2(order, totalCopy,goods,goodsTwoCate,businessName,userName,type,copysort,is_print_all,is_blank_lable);
    }
}

//single label per page
function generateOrderPrint(order, copy,goods,goodsTwoCate,businessName,userName,type,copysort,is_print_all,is_blank_lable) {
    if(is_blank_lable){
        if(is_print_all){
            for (var i = 0; i < copy; i++) {
                order.boxLabel = '__' + " of " + '__';
                //order.boxLabel = i + 88 + " of " + 88;
                addOnePage(order,goods,goodsTwoCate,businessName,userName,type,copysort,is_print_all,is_blank_lable);
            }
            return;
        }else{
            order.boxLabel = '__' + " of " + '__';
            //order.boxLabel = i + 88 + " of " + 88;
            addOnePage(order,goods,goodsTwoCate,businessName,userName,type);
            return;
        }
    }else{
        if(is_print_all){
            for (var i = 0; i < copy; i++) {
                order.boxLabel = i + 1 + " of " + copy;
                //order.boxLabel = i + 88 + " of " + 88;
                addOnePage(order,goods,goodsTwoCate,businessName,userName,type);
            }
            return;
        }else{
            order.boxLabel = copysort + " of " + copy;
            //order.boxLabel = i + 88 + " of " + 88;
            addOnePage(order,goods,goodsTwoCate,businessName,userName,type);
            return;
        }
    }
}
function addOnePage(order,goods,goodsTwoCate,businessName,userName,type) {
    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);
    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body style='font-size:12px' leftmargin=0 topmargin=0>"+labelTemplate(order,goods,goodsTwoCate,businessName,userName,type)+"</body>");
}

//max 3 label per page
function generateOrderPrint2(order, copy,goods,goodsTwoCate,businessName,userName,type) {
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);

    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(5,310,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

    var template = '';
    template += labelTemplate_ThreeLabelPerPage_Main(order, "1 of " + copy,goods,goodsTwoCate,businessName,userName,type);
    if (copy >1) {
        template += "<div style='height:70px'></div>";
        template += labelTemplate_ThreeLabelPerPage_Sub(order, "2 of " + copy,goods,goodsTwoCate,businessName,userName,type);
    }

    /*	if (copy > 2) {
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, "3 of " + copy);
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
            template += labelTemplate_ThreeLabelPerPage_Main(order,  i*2+1 + " of " + copy,goods,goodsTwoCate,businessName,userName,type);
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*2+2+" of " + copy,goods,goodsTwoCate,businessName,userName,type);

            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }

        if (copy%2 == 1) {
            LODOP.NewPage();
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);

            template = ''
            template += labelTemplate_ThreeLabelPerPage_Main(order, copy +" of " + copy,goods,goodsTwoCate,businessName,userName,type);
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
                template += labelTemplate_ThreeLabelPerPage_Main(order,i+2 + " of " + copy,goods,goodsTwoCate,businessName,userName,type);
            } else {
                template += "<div style='height:360px'></div>";
                template += labelTemplate_ThreeLabelPerPage_Sub(order, i+2 +" of " + copy,goods,goodsTwoCate,businessName,userName,type);
            }
            /*template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*3+3 +" of " + copy); */
            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }
    }
}
function labelTemplate(order,goods,goodsTwoCate,businessName,userName,type) {
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
    if(type == 1){
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
        html+='<hr><br>';

        html+='<div>';
        html+='	<label>Order ID:</label>';
        html+='	<span >'+order.orderId+'</span>&nbsp;&nbsp;';

        html+='	<label>Phone:</label>';
        html+='	<span style="float:right;">'+order.phone+'</span>';
        html+='</div>';
        html+='<hr>';
        html+='<label>Suppliers Name:</label>';
        html+='<div style="border: 0px solid black; padding: 5px">';
        //html+='	<span >'+order.logistic_suppliers_info+'</span>';
        html+='	<span >DNL FOOD   license  NO:P01417</span></div>';
        html+='<div style="border: 0px solid black; padding: 5px">	<span >TEL:93988222  0450599336 </span></div>';
        html+='<div style="border: 0px solid black; padding: 5px">	<span >ADD:30 Blaxland Ave, Thomastown VIC 3074</span></div>';
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

function labelTemplate_ThreeLabelPerPage_Main(order, boxLabel,goods,goodsTwoCate,businessName,userName,type) {

}

function labelTemplate_ThreeLabelPerPage_Sub(order, boxLabel,goods,goodsTwoCate,businessName,userName,type) {

}
