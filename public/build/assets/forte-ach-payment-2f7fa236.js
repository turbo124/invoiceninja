<<<<<<<< HEAD:public/build/assets/forte-ach-payment-546428ee.js
var s=Object.defineProperty;var d=(n,e,t)=>e in n?s(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var o=(n,e,t)=>(d(n,typeof e!="symbol"?e+"":e,t),t);import{i,w as u}from"./wait-8f4ae121.js";/**
========
var a=Object.defineProperty;var s=(t,e,n)=>e in t?a(t,e,{enumerable:!0,configurable:!0,writable:!0,value:n}):t[e]=n;var o=(t,e,n)=>(s(t,typeof e!="symbol"?e+"":e,n),n);/**
>>>>>>>> upstream/v5-develop:public/build/assets/forte-ach-payment-2f7fa236.js
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */class d{constructor(e){o(this,"handleAuthorization",()=>{var e=document.getElementById("account-number").value,n=document.getElementById("routing-number").value,r={api_login_id:this.apiLoginId,account_number:e,routing_number:n,account_type:"checking"};return document.getElementById("pay-now")&&(document.getElementById("pay-now").disabled=!0,document.querySelector("#pay-now > svg").classList.remove("hidden"),document.querySelector("#pay-now > span").classList.add("hidden")),forte.createToken(r).success(this.successResponseHandler).error(this.failedResponseHandler),!1});o(this,"successResponseHandler",e=>(document.getElementById("payment_token").value=e.onetime_token,document.getElementById("server_response").submit(),!1));o(this,"failedResponseHandler",e=>{var n='<div class="alert alert-failure mb-4"><ul><li>'+e.response_description+"</li></ul></div>";return document.getElementById("forte_errors").innerHTML=n,document.getElementById("pay-now").disabled=!1,document.querySelector("#pay-now > svg").classList.add("hidden"),document.querySelector("#pay-now > span").classList.remove("hidden"),!1});o(this,"handle",()=>{let e=document.getElementById("pay-now");return e&&e.addEventListener("click",n=>{this.handleAuthorization()}),this});this.apiLoginId=e}}const u=document.querySelector('meta[name="forte-api-login-id"]').content;new d(u).handle();