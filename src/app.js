import Vue from 'vue'
import _ from 'lodash';

import App from './App'
import mixin from './mixin'
// import auth from './auth'
import router from './router'

// globally adding lodash
window._ = _
window.VueExtension = [mixin]

var ExtendedVue = Vue.extend({
    mixins: window.VueExtension
})

// initializing authentication
// auth.init()

/* eslint-disable no-new */
new ExtendedVue({
    el: '#app',
    router,
    template: '<App/>',
    components: { App }
})

