
var __unknown = '不明なエラー';

var VModal = window["vue-js-modal"].default;
Vue.use(VModal);

Vue.directive('init', {
    bind: function (el, binding, vnode) {
        vnode.context[binding.arg] = new String(binding.value);
    }
})


var _sendMsg = new Vue({
    el: '#send-app',
    data: {
        resultMsg :''
    },
    hide: function () {
        this.$modal.hide('send-result');
    },
    methods: {
        show: function (msg) {
            this.resultMsg = msg;
            this.$modal.show('send-result');
        },
        hide: function () {
            this.$modal.hide('send-result');
        }
    }
});



var stripe_modal = new Vue({
    el: '#stripe_modal-app',
    data: {
        cahrge_id : 0,
        post_id : 0,
        sendURL: _ajax_url,
        action : '',
        h2 : '',
        confirm_type : '',
        warning : '全額返金すると注文キャンセルが完了します',
        billing_amount : 0,
        refunds_amount : 0,
        formData: new FormData(),
        hide_time : 2000,
        reload_time : 2500,
        createSendBody: function () {
            var body = '';
            var items = this.items;
            var len = items.length;
            for (var i = 0; i < len; i++) {
                body += '■ ' + items[i].label + ' : ' + items[i].value + '\n';
            }
            return body;
        },
        EncodeHTMLForm: function (data) {
            var params = [];
            for (var name in data) {
                var value = data[name];
                var param = encodeURIComponent(name) + '=' + encodeURIComponent(value);
                params.push(param);
            }
            return params.join('&').replace(/%20/g, '+');
        },
        sendRequest: function ( formData ){

            var _this = this;
            var http = new XMLHttpRequest();

            http.onreadystatechange = function () {

                if (this.readyState == 4 && this.status == 200) {
                    var json = JSON.parse(this.responseText);
                    if (json.res) {
                        // 成功
                        _this.hide(_this.confirm_type);
                        _sendMsg.show(json.success);
                        setTimeout(function () {
                            _sendMsg.hide();
                            location.reload();
                        }, _this.reload_time);
                    } else {
                        // 失敗
                        _sendMsg.show(json.failure);
                        setTimeout(function () {
                            _sendMsg.hide();
                        }, _this.hide_time);

                    }
                }

            }

            http.onerror = function () {
                console.log(http.status);
                console.log("error!");
                _sendMsg.show(__unknown);
                setTimeout(function () {
                    _this.hide(_this.confirm_type);
                    _sendMsg.hide();
                }, _this.hide_time);
            }

            http.open('POST', this.sendURL);
            http.send(formData);

        }

    },
    watch : {

    },
    computed : {
        filterNumber(){
           
        }
    },
    methods: {
        limit : function(e){
            var re = new RegExp(e.target.pattern);
            var result = re.exec(e.target.value);
            return e.target.value = (result) ? result[0] : '';
        },
        show: function () {
            if (this.confirm_type == 'refund' ){
                this.$modal.show('refund-confirm');
            } else if (this.confirm_type == 'auhtorize' ){
                this.$modal.show('refund-confirm');
            } else if (this.confirm_type == 'capture') {
                this.$modal.show('dialog-confirm');
            } else if (this.confirm_type == 'cancel') {
                this.$modal.show('dialog-confirm');
            }    
        },
        hide: function () {
            if (this.confirm_type == 'refund') {
                this.$modal.hide('refund-confirm');
            } else if (this.confirm_type == 'auhtorize') {
                this.$modal.hide('refund-confirm');
            } else if (this.confirm_type == 'capture') {
                this.$modal.hide('dialog-confirm');
            } else if (this.confirm_type == 'cancel') {
                this.$modal.hide('dialog-confirm');
            }    
        },
        commit : function (){
            var cahrge_id = document.getElementById('cahrge_id').value;
                this.cahrge_id = cahrge_id;
            var formData = this.formData;
                formData.append('action', this.action);
                formData.append('cahrge_id', cahrge_id);
                formData.append('amount', this.refunds_amount);
            console.log(this.refunds_amount);
            this.sendRequest( formData );
        },

        send: function () {
            var cahrge_id = document.getElementById('cahrge_id').value;
                this.cahrge_id = cahrge_id;
            var formData = this.formData;
                formData.append('action', this.action);
                formData.append('cahrge_id', cahrge_id);
                formData.append('amount', this.refunds_amount);

            console.log(this.refunds_amount);
            this.sendRequest(formData);
        }
    }
});







document.onreadystatechange = function () {
    if (document.readyState == 'complete') {
        console.log('complete!!');

        var stripeButtons = new Vue({
            el: '#stripeButtons',
            created: function () {

            },
            destroyed: function () {

            },
            data: {
                formItems: {
                    lang: 'ja'
                },
                error_input: function (target, apply) {
                    if (apply) {
                        target.style.border = "1px solid red";
                    } else {
                        target.style = "";
                    }
                    return false;
                },
                sucsess_input: function (target) {
                    target.style.border = "1px solid #8bc34a";
                    return true;
                }
            },           
            methods: {
                exec: function (e) {
                    e.preventDefault();
                    return false;
                },
                refund: function (e) {
                    e.preventDefault();
                    stripe_modal.h2 = '返金';
                    stripe_modal.confirm_type = 'refund';
                    stripe_modal.action = 'vs_refund_stripe';
                    stripe_modal.show();
                    return false;
                },
                cancel : function(e){
                    e.preventDefault();
                    stripe_modal.h2 = '注文キャンセル';
                    stripe_modal.confirm_type = 'cancel';
                    stripe_modal.action = 'vs_cancel_stripe';
                    stripe_modal.show();
                    return false;
                },
                auhtorize: function (e) {
                    e.preventDefault();
                    stripe_modal.h2 = '金額変更';
                    stripe_modal.confirm_type = 'auhtorize';
                    stripe_modal.action = 'vs_auhtorize_stripe';
                    stripe_modal.show();
                    return false;
                },
                capture: function (e) {
                    e.preventDefault();
                    stripe_modal.h2 = '注文確定';
                    stripe_modal.confirm_type = 'capture';
                    stripe_modal.action = 'vs_capture_stripe';
                    stripe_modal.show();
                    return false;
                }, 

            }
        });



    }
};