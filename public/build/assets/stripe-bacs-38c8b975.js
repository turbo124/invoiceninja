<<<<<<<< HEAD:public/build/assets/stripe-bacs-c9a61b93.js
var a=Object.defineProperty;var c=(n,e,t)=>e in n?a(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var s=(n,e,t)=>(c(n,typeof e!="symbol"?e+"":e,t),t);import{i as d,w as u}from"./wait-8f4ae121.js";/**
========
var a=Object.defineProperty;var c=(n,e,t)=>e in n?a(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var s=(n,e,t)=>(c(n,typeof e!="symbol"?e+"":e,t),t);/**
>>>>>>>> upstream/v5-develop:public/build/assets/stripe-bacs-38c8b975.js
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */class d{constructor(e,t){s(this,"setupStripe",()=>(this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key),this));s(this,"payment_data");s(this,"handle",()=>{this.onlyAuthorization?document.getElementById("authorize-bacs").addEventListener("click",e=>{document.getElementById("authorize-bacs").disabled=!0,document.querySelector("#authorize-bacs > svg").classList.remove("hidden"),document.querySelector("#authorize-bacs > span").classList.add("hidden"),location.href=document.querySelector("meta[name=stripe-redirect-url]").content}):(this.payNowButton=document.getElementById("pay-now"),document.getElementById("pay-now").addEventListener("click",e=>{this.payNowButton.disabled=!0,this.payNowButton.querySelector("svg").classList.remove("hidden"),this.payNowButton.querySelector("span").classList.add("hidden"),document.getElementById("server-response").submit()}),this.payment_data=Array.from(document.getElementsByClassName("toggle-payment-with-token")),this.payment_data.length>0?this.payment_data.forEach(e=>e.addEventListener("click",t=>{document.querySelector("input[name=token]").value=t.target.dataset.token})):(this.errors.textContent=document.querySelector("meta[name=translation-payment-method-required]").content,this.errors.hidden=!1,this.payNowButton.disabled=!0,this.payNowButton.querySelector("span").classList.remove("hidden"),this.payNowButton.querySelector("svg").classList.add("hidden")))});this.key=e,this.errors=document.getElementById("errors"),this.stripeConnect=t,this.onlyAuthorization=h}}var o;const u=((o=document.querySelector('meta[name="stripe-publishable-key"]'))==null?void 0:o.content)??"";var r;const l=((r=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:r.content)??"";var i;const h=((i=document.querySelector('meta[name="only-authorization"]'))==null?void 0:i.content)??"";new d(u,l).setupStripe().handle();