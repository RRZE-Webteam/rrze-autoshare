(()=>{"use strict";var e={n:t=>{var r=t&&t.__esModule?()=>t.default:()=>t;return e.d(r,{a:r}),r},d:(t,r)=>{for(var a in r)e.o(r,a)&&!e.o(t,a)&&Object.defineProperty(t,a,{enumerable:!0,get:r[a]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t),r:e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})}},t={};e.r(t),e.d(t,{setAllowTweetImage:()=>C,setAutoshareEnabled:()=>S,setErrorMessage:()=>f,setLoaded:()=>z,setOverriding:()=>x,setSaving:()=>y,setTweetAccounts:()=>L,setTweetText:()=>P});var r={};e.r(r),e.d(r,{getAllowTweetImage:()=>O,getAutoshareEnabled:()=>N,getErrorMessage:()=>I,getOverriding:()=>k,getSaving:()=>M,getTweetAccounts:()=>j,getTweetText:()=>D});const a=window.React,n=window.wp.element,s=window.wp.plugins,o=window.wp.editPost,l=window.wp.data,c=window.wp.components,i=window.wp.i18n,u="SET_AUTOSHARE_TWITTER_ENABLED",d="SET_ERROR_MESSAGE",g="SET_LOADED",h="SET_OVERRIDING",m="SET_SAVING",w="SET_TWEET_TEXT",p="SET_ALLOW_TWEET_IMAGE",E="SET_TWEET_ACCOUNTS",{enabled:T,allowTweetImage:b,tweetAccounts:_,customTweetBody:v}=adminAutoshareTwitter,A={autoshareEnabled:!!T&&"0"!==T,errorMessage:"",loaded:!1,overriding:!!v,overrideLength:0,tweetText:v||"",allowTweetImage:!!b,tweetAccounts:_||[]};const S=e=>({type:u,autoshareEnabled:e}),f=e=>({type:d,errorMessage:e}),z=()=>({type:g}),x=e=>({type:h,overriding:e}),y=e=>({type:m,saving:e}),P=e=>({type:w,tweetText:e}),C=e=>({type:p,allowTweetImage:e}),L=e=>({type:E,tweetAccounts:e}),N=e=>e.autoshareEnabled,I=e=>e.errorMessage,k=e=>e.overriding,M=e=>e.saving,D=e=>e.tweetText,O=e=>e.allowTweetImage,j=e=>e.tweetAccounts||[],B="rrze/autoshare",F=window.wp.primitives,R=e=>(0,a.createElement)(F.SVG,{version:"1.1",id:"Twitter",x:"0px",y:"0px",width:"144.083px",height:"144px",viewBox:"0 0 144.083 144"},(0,a.createElement)(F.Path,{d:"M72.04,14.165c-31.94,0-57.833,25.894-57.833,57.834c0,31.939,25.893,57.836,57.833,57.836  c31.941,0,57.835-25.896,57.835-57.836C129.875,40.059,103.981,14.165,72.04,14.165z M100.272,57.631c0,0,1.375,6.815-3.559,18.787  c-4.936,11.972-17.15,23.297-33.974,24.349c-16.825,1.051-25.887-6.391-25.887-6.391c12.62,0.971,18.201-4.206,21.194-5.905  c-11.729-1.537-13.428-10.354-13.428-10.354c1.132,0.486,4.61,0.324,6.471-0.161c-11.485-2.831-11.728-14.398-11.728-14.398  s4.286,2.184,6.713,1.779c-11.083-8.413-4.61-19.414-4.61-19.414c14.641,16.017,30.171,15.127,30.171,15.127l0.07,0.105  c-0.282-1.128-0.434-2.308-0.434-3.523c0-8.008,6.492-14.499,14.5-14.499c4.23,0,8.037,1.813,10.688,4.703l0.092-0.052  c5.176-0.324,8.979-3.074,8.979-3.074c-1.576,4.604-4.924,6.749-6.322,7.471c0.018,0.043,0.035,0.086,0.051,0.128  c1.809-0.438,6.662-1.625,7.971-2.017C104.397,55.468,100.272,57.631,100.272,57.631z",fill:e})),U=R("#1B1C20"),G=R("#1DA1F2"),W=R("#787E88"),$=R("#D0494A"),K=R("#7FD051"),V=e=>{let t=U;return e&&(t="published"===e?K:"error"===e?$:U),(0,a.createElement)(c.Icon,{className:"rrze-autoshare-twitter-icon",icon:t,size:48})},H=window.wp.apiFetch;var q=e.n(H);const X=window.lodash,{enableAutoshareKey:Y,errorText:J,restUrl:Q,tweetBodyKey:Z,allowTweetImageKey:ee,tweetAccountsKey:te}=adminAutoshareTwitter;function re(){const{tweetText:e}=(0,l.useSelect)((e=>({tweetText:e(B).getTweetText()}))),{setTweetText:t}=(0,l.useDispatch)(B);return[e,t]}function ae(){const{autoshareEnabled:e}=(0,l.useSelect)((e=>({autoshareEnabled:e(B).getAutoshareEnabled()}))),{setAutoshareEnabled:t}=(0,l.useDispatch)(B);return[e,t]}function ne(){const{allowTweetImage:e}=(0,l.useSelect)((e=>({allowTweetImage:e(B).getAllowTweetImage()}))),{setAllowTweetImage:t}=(0,l.useDispatch)(B);return[e,t]}function se(){const{tweetAccounts:e}=(0,l.useSelect)((e=>({tweetAccounts:e(B).getTweetAccounts()}))),{setTweetAccounts:t}=(0,l.useDispatch)(B);return[e,t]}function oe(){const{errorMessage:e}=(0,l.useSelect)((e=>({errorMessage:e(B).getErrorMessage()}))),{setErrorMessage:t}=(0,l.useDispatch)(B);return[e,t]}function le(){const{imageId:e}=(0,l.useSelect)((e=>({imageId:e("core/editor").getEditedPostAttribute("featured_media")})));return e>0}function ce(){const[e]=ae(),[t]=ne(),[r]=se(),[a]=re(),[,s]=oe(),[,o]=function(){const{saving:e}=(0,l.useSelect)((e=>({saving:e(B).getSaving()})));return[e,function(e){(0,l.dispatch)(B).setSaving(e),e?(0,l.dispatch)("core/editor").lockPostSaving():(0,l.dispatch)("core/editor").unlockPostSaving()}]}(),{hasFeaturedImage:c}=(0,l.useSelect)((e=>({hasFeaturedImage:e("core/editor").getEditedPostAttribute("featured_media")>0}))),u=(0,n.useCallback)((0,X.debounce)((async function(e,t,r,a){const n={};n[Y]=e,n[Z]=t,n[ee]=r,n[te]=a||[];try{o(!0);const e=await q()({url:Q,data:n,method:"POST",parse:!1});if(!e.ok)throw e;await e.json(),s(""),o(!1)}catch(e){s(e.statusText?`${J} ${e.status}: ${e.statusText}`:(0,i.__)("An error occurred.","rrze-autoshare")),o(!1)}}),250),[]);(0,n.useEffect)((()=>{u(e,a,t,r)}),[e,a,c,t,r,u])}const{siteUrl:ie,isLocalSite:ue,twitterURLLength:de}=adminAutoshareTwitter;function ge(){const e=e=>{if(!ue&&!isNaN(de))return Number(de);const t=e("core/editor").getPermalink();if(t)return t.length;const r=e("core/editor").getEditedPostAttribute("title");return r&&"rendered"in r?(ie+r.rendered).length:ie.length},{permalinkLength:t,maxLength:r}=(0,l.useSelect)((t=>({permalinkLength:e(t),maxLength:275-e(t)}))),[s,o]=re(),{tweetLength:u,overrideLengthClass:d}=(()=>{const e=t+s.length+5;return 280<=e?{tweetLength:(0,i.sprintf)(/* translators: %d is tweet message character count */
(0,i.__)("%d - Too Long!","rrze-autoshare"),e),overrideLengthClass:"over-limit"}:240<=e?{tweetLength:(0,i.sprintf)(/* translators: %d is tweet message character count */
(0,i.__)("%d - Getting Long!","rrze-autoshare"),e),overrideLengthClass:"near-limit"}:{tweetLength:`${e}`,overrideLengthClass:""}})(),g=(0,l.useSelect)((e=>e("core/editor").getEditedPostAttribute("status"))),[h,m]=(0,n.useState)("publish"===g);return(0,n.useEffect)((()=>{"publish"!==g||h||(o(""),m(!0))}),[g,h]),(0,a.createElement)(c.TextareaControl,{value:s,onChange:e=>{o(e)},className:"rrze-autoshare-twitter-tweet-text",maxLength:r,label:(0,a.createElement)("span",{style:{marginTop:"0.5rem",display:"block"},className:"rrze-autoshare-twitter-prepublish__message-label"},(0,a.createElement)("span",null,(0,i.__)("Custom message:","rrze-autoshare")," "),(0,a.createElement)("span",{id:"rrze-autoshare-twitter-counter-wrap",className:`alignright ${d}`},(0,a.createElement)((()=>(0,a.createElement)(c.Tooltip,{text:(0,i.__)("Count is inclusive of the post permalink which will be included in the final tweet.","rrze-autoshare")},(0,a.createElement)("div",null,u))),null)))})}const{connectedAccounts:he,connectAccountUrl:me}=adminAutoshareTwitter;function we(){const e=he?Object.values(he):[];return(0,a.createElement)("div",{className:"rrze-autoshare-twitter-accounts-wrapper"},e.map((e=>(0,a.createElement)(pe,{key:e.id,...e}))),(0,a.createElement)("span",{className:"connect-account-link"},(0,a.createElement)(c.ExternalLink,{href:me},(0,i.__)("Connect an account","rrze-autoshare"))))}function pe(e){const[t,r]=se(),{id:n,name:s,username:o,profile_image_url:l}=e;return(0,a.createElement)("div",{className:"twitter-account-wrapper"},(0,a.createElement)("img",{src:l,alt:s,className:"twitter-account-profile-image"}),(0,a.createElement)("span",{className:"account-details"},(0,a.createElement)("strong",null,"@",o),(0,a.createElement)("br",null),s),(0,a.createElement)(c.ToggleControl,{checked:t&&t.includes(n),onChange:e=>{r(e?[...t,n]:t.filter((e=>e!==n)))},className:"rrze-autoshare-twitter-account-toggle"}))}const Ee=({errorMessage:e})=>(0,a.createElement)("span",null,e," ",e?.includes("When authenticating requests to the Twitter API v2 endpoints, you must use keys and tokens from a Twitter developer App that is attached to a Project. You can create a project via the developer portal.")&&(0,a.createElement)(c.ExternalLink,{href:"https://developer.twitter.com/en/docs/twitter-api/migrate/ready-to-migrate"},(0,i.__)("Learn more here.","rrze-autoshare")));function Te({messages:e}){return e&&e.message.length?(0,a.createElement)("div",{className:"rrze-autoshare-twitter-post-status"},e.message.map(((e,t)=>{const r=V(e.status);return(0,a.createElement)("div",{className:"rrze-autoshare-twitter-log",key:t},r,(0,a.createElement)("span",null,e.url?(0,a.createElement)(c.ExternalLink,{href:e.url},e.message):(0,a.createElement)(Ee,{errorMessage:e.message}),!!e.handle&&(0,a.createElement)("strong",null," - @"+e.handle)))})),(0,a.createElement)(c.CardDivider,null)):null}function be(){const[e,t]=ae(),[r,n]=function(){const{overriding:e}=(0,l.useSelect)((e=>({overriding:e(B).getOverriding()}))),{setOverriding:t}=(0,l.useDispatch)(B);return[e,t]}(),[s,o]=ne(),[u]=oe(),d=le(),g=(0,l.select)("core/editor").getCurrentPostAttribute("rrze_autoshare_twitter_status");return ce(),(0,a.createElement)(a.Fragment,null,(0,a.createElement)(Te,{messages:g}),(0,a.createElement)(c.ToggleControl,{label:e?(0,i.__)("Tweet when published","rrze-autoshare"):(0,i.__)("Don't Tweet","rrze-autoshare"),checked:e,onChange:e=>{t(e)},className:"rrze-autoshare-twitter-toggle-control"}),e&&d&&(0,a.createElement)(c.ToggleControl,{label:(0,i.__)("Use featured image in Tweet","rrze-autoshare"),checked:s,onChange:()=>{o(!s)},className:"rrze-autoshare-twitter-toggle-control"}),e&&(0,a.createElement)(we,null),e&&(0,a.createElement)("div",{className:"rrze-autoshare-twitter-prepublish__override-row"},r&&(0,a.createElement)(ge,null),(0,a.createElement)(c.Button,{isLink:!0,onClick:()=>{n(!r)}},r?(0,i.__)("Hide","rrze-autoshare"):(0,i.__)("Edit","rrze-autoshare"))),(0,a.createElement)("div",null,u))}const _e=(0,window.wp.compose.compose)((0,l.withSelect)((e=>({statusMessage:e("core/editor").getCurrentPostAttribute("rrze_autoshare_twitter_status")}))))((function(){const e=le(),[t,r]=ne(),[,s]=re(),[o,u]=(0,n.useState)(!1),[d,g]=(0,n.useState)(!1),{messages:h}=(0,l.useSelect)((e=>({messages:e("core/editor").getCurrentPostAttribute("rrze_autoshare_twitter_status")}))),[m,w]=(0,n.useState)(h);if(ce(),m&&!m.message.length)return null;const p=(0,a.createElement)(c.Icon,{icon:(0,a.createElement)("svg",{viewBox:"0 0 28 28",xmlns:"http://www.w3.org/2000/svg",width:"28",height:"28","aria-hidden":"true",focusable:"false"},(0,a.createElement)("path",{d:"M6.5 12.4L12 8l5.5 4.4-.9 1.2L12 10l-4.5 3.6-1-1.2z"}))}),E=(0,a.createElement)(c.Icon,{icon:(0,a.createElement)("svg",{viewBox:"0 0 28 28",xmlns:"http://www.w3.org/2000/svg",width:"28",height:"28","aria-hidden":"true",focusable:"false"},(0,a.createElement)("path",{d:"M17.5 11.6L12 16l-5.5-4.4.9-1.2L12 14l4.5-3.6 1 1.2z"}))});return(0,a.createElement)(a.Fragment,null,(0,a.createElement)(Te,{messages:m}),(0,a.createElement)(c.Button,{className:"rrze-autoshare-twitter-tweet-now",variant:"link",text:(0,i.__)("Tweet now","rrze-autoshare"),onClick:()=>g(!d),iconPosition:"right",icon:d?p:E}),d&&(0,a.createElement)(a.Fragment,null,e&&(0,a.createElement)(c.ToggleControl,{label:(0,i.__)("Use featured image in Tweet","rrze-autoshare"),checked:t,onChange:()=>{r(!t)},className:"rrze-autoshare-twitter-toggle-control"}),(0,a.createElement)(we,null),(0,a.createElement)(ge,null),(0,a.createElement)(c.Button,{variant:"primary",className:"rrze-autoshare-twitter-re-tweet",text:o?(0,i.__)("Tweeting…","rrze-autoshare"):(0,i.__)("Tweet again","rrze-autoshare"),onClick:()=>{(async()=>{u(!0);const e=await(0,l.select)("core/editor").getCurrentPostId(),t=new FormData;t.append("action",adminAutoshareTwitter.retweetAction),t.append("nonce",adminAutoshareTwitter.nonce),t.append("post_id",e);const r=await fetch(ajaxurl,{method:"POST",body:t}),{data:a}=await r.json();a.is_retweeted&&s(""),w(a),u(!1)})()}})))}));(0,l.registerStore)(B,{reducer:function(e=A,t){switch(t.type){case u:return{...e,autoshareEnabled:t.autoshareEnabled};case d:return{...e,errorMessage:t.errorMessage};case g:return{...e,loaded:!0};case h:return{...e,overriding:t.overriding};case m:return{...e,saving:t.saving};case w:return{...e,tweetText:t.tweetText};case p:return{...e,allowTweetImage:t.allowTweetImage};case E:return{...e,tweetAccounts:t.tweetAccounts}}},actions:t,selectors:r});class ve extends n.Component{constructor(e){super(e),this.state={enabledText:""},this.maybeSetEnabledText=this.maybeSetEnabledText.bind(this)}componentDidMount(){(0,l.dispatch)(B).setLoaded(),(0,l.subscribe)(this.maybeSetEnabledText)}maybeSetEnabledText(){try{const e=(0,l.select)(B).getAutoshareEnabled(),t=e?(0,i.__)("This post will be Tweeted","rrze-autoshare"):(0,i.__)("This post will not be Tweeted","rrze-autoshare");t!==this.state.enabledText&&this.setState({enabled:e,enabledText:t})}catch(e){}}render(){const{enabled:e,enabledText:t}=this.state,r=e?G:W,n=(0,a.createElement)(c.Icon,{className:"rrze-autoshare-twitter-icon components-panel__icon",icon:r,size:24});return(0,a.createElement)(o.PluginPrePublishPanel,{title:t,icon:n,className:"rrze-autoshare-twitter-pre-publish-panel"},(0,a.createElement)(be,null))}}class Ae extends n.Component{constructor(e){super(e),this.state={enabledText:""},this.maybeSetEnabledText=this.maybeSetEnabledText.bind(this)}componentDidMount(){(0,l.dispatch)(B).setLoaded(),(0,l.subscribe)(this.maybeSetEnabledText)}maybeSetEnabledText(){try{const e=(0,l.select)(B).getAutoshareEnabled(),t=e?(0,i.__)("Autotweet enabled","rrze-autoshare"):(0,i.__)("Autotweet disabled","rrze-autoshare");t!==this.state.enabledText&&this.setState({enabledText:t,enabled:e})}catch(e){}}render(){if("publish"===(0,l.select)("core/editor").getCurrentPostAttribute("status")){const e=(0,l.select)("core/editor").getCurrentPostAttribute("rrze_autoshare_twitter_status");let t="";return e&&e.message&&e.message.length&&(t=e.message[e.message.length-1].status||""),(0,a.createElement)(o.PluginDocumentSettingPanel,{title:(0,i.__)("Autotweet","rrze-autoshare"),icon:V(t),className:"rrze-autoshare-twitter-editor-panel"},(0,a.createElement)(_e,null))}const{enabled:e,enabledText:t}=this.state,r=e?G:W,n=(0,a.createElement)(c.Icon,{className:"rrze-autoshare-twitter-icon components-panel__icon",icon:r,size:24});return(0,a.createElement)(o.PluginDocumentSettingPanel,{title:t,icon:n,className:"rrze-autoshare-editor-panel"},(0,a.createElement)(be,null))}}(0,s.registerPlugin)("rrze-autoshare-twitter-editor-panel",{render:Ae}),(0,s.registerPlugin)("rrze-autoshare-twitter-pre-publish-panel",{render:ve}),(0,s.registerPlugin)("rrze-autoshare-twitter-post-publish-panel",{render:()=>(0,a.createElement)(o.PluginPostPublishPanel,{className:"rrze-autoshare-twitter-post-status-info"},(0,a.createElement)(_e,null))})})();