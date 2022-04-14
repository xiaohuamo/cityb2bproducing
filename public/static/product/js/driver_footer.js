Vue.component('common-footer',{
    props: ['currentMenu'],
    data:{
    },
    mounted: function () {
        //如果当前在我的页面，用户刚登录进来，先获取登录信息
        if(this.currentMenu != 5){
            //校验用户登录信息是否正确
            var data = {
                "business_id":decodeBase64(localStorage.getItem('business_id')),
                "user_id":decodeBase64(localStorage.getItem('user_id')),
                "type":"loginCheck"
            }
            // console.log('获取登录校验参数---',data);
            getData(common.driverWebUrl+'/product/driverLoginInfo', data, function (res) {
                // console.log('获取登录校验结果---',res);
                if(res.status == 134){
                    //清空本地存储信息，并跳转到登录页面
                    localStorage.clear();
                    window.location.href=common.driverWebUrl+'product/login'
                }
            });
        }
    },
    methods:{
        changeMenu:function(index){
            switch(index){
                case 1://登记
                    window.location.href=common.driverWebUrl+'product/check_in'
                    break;
                case 2://收货
                    window.location.href=common.driverWebUrl+'product/order'
                    break;
                case 3://导航
                    window.location.href=common.driverWebUrl+'product/customerSearch'
                    break;
                case 4://收工
                    window.location.href=common.driverWebUrl+'product/knock_off'
                    break;
                case 5://我的
                	window.location.href=common.driverWebUrl+'product/me'
                	break;
                default:
            }
        },
    },
    template:`<div><div style="height:3.125rem;"></div>
    <div class="flexBox1 menuBox">
      <div @click="changeMenu(1)">
        <img src="../static/product/img/menu3_.png" v-if="currentMenu==1"/>
        <img src="../static/product/img/menu3.png" v-else/>
        <span class="f26" :class="currentMenu==1?'colFD5506':'col333'">register</span>
      </div>
      <div @click="changeMenu(2)">
        <img src="../static/product/img/driverMenu2_.png" v-if="currentMenu==2"/>
        <img src="../static/product/img/driverMenu2.png" v-else/>
        <span class="f26" :class="currentMenu==2?'colFD5506':'col333'">receipt</span>
      </div>
      <div @click="changeMenu(3)">
        <img src="../static/product/img/driverMenu1_.png" v-if="currentMenu==3"/>
        <img src="../static/product/img/driverMenu1.png" v-else/>
        <span class="f26" :class="currentMenu==3?'colFD5506':'col333'">navigation</span>
      </div>
      <div @click="changeMenu(4)">
        <img src="../static/product/img/driverMenu4_.png" v-if="currentMenu==4"/>
        <img src="../static/product/img/driverMenu4.png" v-else/>
        <span class="f26" :class="currentMenu==4?'colFD5506':'col333'">knock off</span>
      </div>
      <div @click="changeMenu(5)">
        <img src="../static/product/img/menu5_.png" v-if="currentMenu==5"/>
        <img src="../static/product/img/menu5.png" v-else/>
        <span class="f26" :class="currentMenu==5?'colFD5506':'col333'">mine</span>
      </div></div>`
});
