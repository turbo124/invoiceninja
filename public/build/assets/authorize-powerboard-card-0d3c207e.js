import{i as d,w as c}from"./wait-8f4ae121.js";/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */function s(){const n=document.querySelector("meta[name=public_key]"),o=document.querySelector("meta[name=gateway_id]"),e=new cba.HtmlWidget("#widget",n==null?void 0:n.content,o==null?void 0:o.content);e.setEnv("{{ $environment }}"),e.useAutoResize(),e.onFinishInsert('input[name="gateway_response"]',"payment_source"),e.load();function r(){let a=document.getElementById("pay-now");a.disabled=e.isInvalidForm()}e.trigger("tab",r),e.trigger("submit_form",r),e.trigger("tab",r),e.on("submit",async function(a){document.getElementById("errors").hidden=!0});const t=document.getElementById("authorize-card");t.addEventListener("click",()=>{t.disabled=!0,t.querySelector("svg").classList.remove("hidden"),t.querySelector("span").classList.add("hidden"),document.getElementById("server-response").submit()});const i=document.querySelector('input[name="payment-type"]');i&&i.click()}d()?s():c("#foo").then(s);
