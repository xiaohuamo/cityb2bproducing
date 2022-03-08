
function preview(parsedOrders,totalCopy=1) {
    if(parsedOrders.first_name || parsedOrders.last_name){
        parsedOrders.nickname_print = parsedOrders.nickname+'('+parsedOrders.last_name+' '+parsedOrders.first_name+')'
    }
    init(parsedOrders,totalCopy);
    LODOP.PREVIEW();
}

var LODOP;
var PRINT_MODE_SINGLE_LABEL_PER_PAGE = 'SINGLE_LABEL_PER_PAGE';
var PRINT_MODE_THREE_LABEL_PER_PAGE = 'THREE_LABEL_PER_PAGE';
var DEFAULT_PRINT_MODE = PRINT_MODE_SINGLE_LABEL_PER_PAGE;

function init(order,totalCopy) {
    LODOP = getLodop();
    LODOP.PRINT_INIT("CityB2B-打印机预览");
    LODOP.SET_PRINT_PAGESIZE(1, '3.6 in', '7 in', "");
    if (DEFAULT_PRINT_MODE === PRINT_MODE_SINGLE_LABEL_PER_PAGE) {
        generateOrderPrint(order, totalCopy);
    } else if (DEFAULT_PRINT_MODE === PRINT_MODE_THREE_LABEL_PER_PAGE) {
        generateOrderPrint2(order, totalCopy);
    }
}

//single label per page
function generateOrderPrint(order, copy) {
    for (var i = 0; i < copy; i++) {
        order.boxLabel = i + 1 + " of " + copy;
        addOnePage(order);
    }
}
function addOnePage(order) {
    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);
    LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body style='font-size:12px' leftmargin=0 topmargin=0>"+labelTemplate(order)+"</body>");
}

//max 3 label per page
function generateOrderPrint2(order, copy) {
    LODOP.SET_PRINT_STYLEA(0,"Stretch",2);

    LODOP.NewPage();
    //QR CODE
    var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
    LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);
    // LODOP.ADD_PRINT_IMAGE(0,250,60,60,"<img border='0' src='http://www.lodop.net/demolist/PrintSample8.jpg' />");

    var template = '';
    template += labelTemplate_ThreeLabelPerPage_Main(order, "1 of " + copy);
    if (copy >1) {
        template += "<div style='height:70px'></div>";
        template += labelTemplate_ThreeLabelPerPage_Sub(order, "2 of " + copy);
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
            template += labelTemplate_ThreeLabelPerPage_Main(order,  i*2+1 + " of " + copy);
            template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*2+2+" of " + copy);

            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }

        if (copy%2 == 1) {
            LODOP.NewPage();
            var qrvalue = 'https://www.cityb2b.com/company/customer_order_redeem_qrscan?qrscanredeemcode=' + order.redeem_code;
            LODOP.ADD_PRINT_BARCODE(0,280,60,60,"QRCode",qrvalue);

            template = ''
            template += labelTemplate_ThreeLabelPerPage_Main(order, copy +" of " + copy);
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
                template += labelTemplate_ThreeLabelPerPage_Main(order,i+2 + " of " + copy);
            } else {
                template += "<div style='height:360px'></div>";
                template += labelTemplate_ThreeLabelPerPage_Sub(order, i+2 +" of " + copy);
            }
            /*template += "<div style='height:70px'></div>";
            template += labelTemplate_ThreeLabelPerPage_Sub(order, i*3+3 +" of " + copy); */
            LODOP.ADD_PRINT_HTM(0, 0, "100%","100%","<body  style='font-size:12px' leftmargin=0 topmargin=0>"+template+"</body>");
        }
    }
}
function labelTemplate(order) {
    console.log(order)
    var html = '';

    html+='<p>offline|Delivery  CustId:'+order.userId+ '<br>CustName:<strong  style=\"width: 100%;font-size:25px;font-weight:bolder\" >'+order.nickname_print+'</strong></p>';
    html+='<table style="width: 100%;font-size:30px;font-weight:bolder" cellspacing="0" cellpadding="0">';
    html+='<tr>';
    html+=	'<td style="font-size:18px ;border-width: 2px 1px 1px 2px;border-style:solid;text-align: left; ">&nbsp;&nbsp;'+new Date(order.logistic_delivery_date*1000).toLocaleDateString("en-US")+'</td>';
    html+=	'<td style=" border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;"><span style="font-size:18px">Drop No:&nbsp;&nbsp;</span>'+order.logistic_stop_No+'&nbsp;&nbsp;</td>';
    html+='</tr>';
    html+='<tr>';
    html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: left;" >&nbsp;&nbsp;'+order.logistic_sequence_No+'</td>';
    html+=	'<td style="border-width: 1px 1px 1px 1px;border-style:solid;text-align: right;" >&nbsp;<span style="font-size:18px">Box </span> '+order.boxLabel+'&nbsp;&nbsp;</td>';

    html+='</tr>';
    html+='</table>';

    html+='<br>';
    html+='	<label>Deliver Address:</label>';
    html+='<br>';
    html+='<div style="border: 3px solid black; padding: 5px">';
    html+='	<span style="font-weight: bolder;">'+order.address + '</span>';

    html+='</div>';
    html+='<div>';
    html+='	<label>Note:</label>  ';
    html+='	<small>'+order.message_to_business+'</small>';
    html+='</div>';
    html+='<br><br>';
    html+='<hr><br>';

    html+='<div>';
    html+='	<label>Order ID:</label>';
    html+='	<span style="float:right;">'+order.orderId+'</span>';
    html+='</div>';
    html+='<div>';
    html+='	<label>Name:</label>';
    html+='	<span style="float:right;">'+order.first_name+' '+order.last_name+'</span>';
    html+='</div>';
    html+='<div>';
    html+='	<label>Phone:</label>';
    html+='	<span style="float:right;">'+order.phone+'</span>';
    html+='</div>';

    html+='<div>';
    html+='	<label>Truck No:</label>';
    html+='	<span style="float:right;">'+order.logistic_truck_No+'</span>';
    html+='</div>';
    html+='<div>';
    html+='	<label>Stop No:</label>';
    html+='	<span style="float:right;">'+order.logistic_stop_No+'</span>';
    html+='</div>';
    html+='<br><hr><br>';
    html+='<div>';
    html+='	<label>Suppliers Count:</label>';
    html+='	<span style="float:right;">'+order.logistic_suppliers_count+'</span>';
    html+='</div>';
    html+='<label>Suppliers Name:</label>';
    html+='<div style="border: 1px solid black; padding: 5px">';
    html+='	<span >'+order.logistic_suppliers_info+'</span>';
    html+='</div>';

    return html;
}

function labelTemplate_ThreeLabelPerPage_Main(order, boxLabel) {

}

function labelTemplate_ThreeLabelPerPage_Sub(order, boxLabel) {

}
