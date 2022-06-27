Vue.component('common-footer',{
    props: ['currentMenu'],
    data:{
    },
    mounted: function () {
    },
    methods:{
        changeMenu:function(index){
            if(localStorage.getItem('token') != null || index == 5){
                switch(index){
                    case 1://登记
                        window.location.href=common.driverWebUrl+'driver/startJob'
                        break;
                    case 2://收货
                        window.location.href=common.driverWebUrl+'driver/order'
                        break;
                    case 3://导航
                        window.location.href=common.driverWebUrl+'driver/customerSearch'
                        break;
                    case 4://收工
                        window.location.href=common.driverWebUrl+'driver/jobDone'
                        break;
                    case 5://我的
                        window.location.href=common.driverWebUrl+'driver/me'
                        break;
                    default:
                }
            }else{
                window.location.href = common.driverWebUrl+'/driver/login'
            }
        },
    },
    template:`<div><div style="height:3.125rem;"></div>
    <div class="flexBox1 menuBox">
      <div @click="changeMenu(1)">
        <img src="../static/product/img/menu3_.png" v-if="currentMenu==1"/>
        <img src="../static/product/img/menu3.png" v-else/>
        <span class="f26" :class="currentMenu==1?'colFD5506':'col333'">Start Job</span>
      </div>
      <div @click="changeMenu(2)">
        <img src="../static/product/img/driverMenu2_.png" v-if="currentMenu==2"/>
        <img src="../static/product/img/driverMenu2.png" v-else/>
        <span class="f26" :class="currentMenu==2?'colFD5506':'col333'">Receive</span>
      </div>
      <div @click="changeMenu(3)">
        <img src="../static/product/img/driverMenu1_.png" v-if="currentMenu==3"/>
        <img src="../static/product/img/driverMenu1.png" v-else/>
        <span class="f26" :class="currentMenu==3?'colFD5506':'col333'">Navigation</span>
      </div>
      <div @click="changeMenu(4)">
        <img src="../static/product/img/driverMenu4_.png" v-if="currentMenu==4"/>
        <img src="../static/product/img/driverMenu4.png" v-else/>
        <span class="f26" :class="currentMenu==4?'colFD5506':'col333'">Job Done</span>
      </div>
      <div @click="changeMenu(5)">
        <img src="../static/product/img/menu5_.png" v-if="currentMenu==5"/>
        <img src="../static/product/img/menu5.png" v-else/>
        <span class="f26" :class="currentMenu==5?'colFD5506':'col333'">Me</span>
      </div></div>`
});
