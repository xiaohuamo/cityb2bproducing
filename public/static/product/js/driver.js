new Vue({
    el:"#main",
    data:{
        currentMenu:4,   // 当前菜单

        isSH:true,
        current:0,
        choseData1:'',
        choseData2:'',
        list1:['司机1','司机2'],
        list2:['2021-09-09','2021-09-09'],

        list:['供应商1','供应商2'],
        searchCon:'',
        isChose:false,
        choseData:'',
        currentMenu:3   // 当前菜单
    },
    methods:{
        changeMenu:function(index){
            this.currentMenu=index
            switch(index){
                case 1://登记
                    window.location.href=common.driverWebUrl+'product/check_in'
                break;
                case 2://收货
                    window.location.href=common.driverWebUrl+'product/order'
                break;
                case 3://导航
                    window.location.href="{:url('/product/checkin')}"
                break;
                case 4://收工
                    window.location.href=common.driverWebUrl+'product/knock_off'
                break;
                // case 5:
                // 	window.location.href="{:url('/product/checkin')}"
                // 	break;
                default:
            }
        },
        SH:function(){  // 標記收穫
            // this.isSH=false
            window.location.href=common.driverWebUrl+'product/confirmRecept'
        },
        choseData:function(source,data){
            if(source=='1'){
                this.choseData1=data
            }else{
                this.choseData2=data
            }
            this.current=0
        },
        chose:function(index){
            if(index=='3'||index=='4'){
                alert('更新 data')
            }else{
                this.current=index
            }
        },
        close:function(){
            this.current=0
        },
        search:function(){
            if(this.searchCon!=''){
                this.isShowSearchRes=true
                this.isShowHistory=false
            }else{
                this.isShowHistory=true
                this.isShowSearchRes=false
            }
        },
        remove:function(){
            this.searchCon=''
        },
        Chose:function(){
            this.isChose=!this.isChose
        },
        Chose2:function(data){
            this.choseData=data
        },
        customerSearch(){
            window.location.href=common.driverWebUrl+'product/customerSearch'
        }
    }
})
