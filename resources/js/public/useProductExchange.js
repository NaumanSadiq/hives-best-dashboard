import {useLayoutEffect,useRef} from 'react';
import {flushSync} from 'react-dom';
const BASE=240,DURATION=680,EASING='cubic-bezier(.3,.7,.22,1)';
export function useProductExchange(stageRef,order,onSettled){
 const running=useRef([]),last=useRef(null),revision=useRef(0),monitorFrame=useRef(0),orderRef=useRef(order),settleRef=useRef(onSettled),layoutRef=useRef(null);
 orderRef.current=order;settleRef.current=onSettled;
 useLayoutEffect(()=>{
  const stage=stageRef.current;if(!stage)return;const root=stage.closest('.golden-page');let measured='';
  function layout(animate){
   const rect=stage.getBoundingClientRect(),ids=orderRef.current,token=++revision.current,isGhee=ids[0]==='ghee';
   measured=rect.width+':'+rect.height;cancelAnimationFrame(monitorFrame.current);monitorFrame.current=0;
   const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches,changed=last.current&&ids.some((id,i)=>id!==last.current[i]),moving=Boolean(animate&&!reduced&&changed);
   // Capture every animated value before cancelling: interruptions continue from the visible frame.
   const plans=[];
   function plan(el,target,settle=false){const style=getComputedStyle(el),from={};for(const key of Object.keys(target))from[key]=style[key];plans.push({el,from,target,settle});}
   for(const el of stage.querySelectorAll('.showcase-jar')){
    const index=ids.indexOf(el.dataset.product),slot=stage.querySelector('.jar-slot[data-slot="'+index+'"]').getBoundingClientRect(),x=slot.left-rect.left,y=slot.top-rect.top,scale=slot.width/BASE;
    plan(el,{transform:'translate3d('+x+'px,'+y+'px,0) scale('+scale+')'},index===0);
    el.dataset.slot=String(index);el.style.zIndex=index===0?'4':'2';
   }
   const backdrop=isGhee?'rgb(248, 219, 114)':'rgb(239, 188, 84)';
   for(const el of root.querySelectorAll('.hero-zone,.header-zone'))plan(el,{backgroundColor:backdrop});
   plan(stage.querySelector('.stage-shadow'),{transform:'translateX('+(isGhee?-3:0)+'px) scale('+(isGhee?.94:1)+')',opacity:isGhee?'.2':'.24'});
   plan(stage.querySelector('.stage-glow'),{transform:isGhee?'translateX(12px) scale(1.06)':'translateX(0px) scale(1)',opacity:isGhee?'.95':'.7'});
   plan(stage.querySelector('.comb-photo'),{transform:isGhee?'translate(-16px, 12px) rotate(-8deg)':'translate(0px, 0px) rotate(0deg)',opacity:isGhee?'0':'.95'});
   plan(stage.querySelector('.ghee-photo'),{transform:isGhee?'translate(0px, 0px) rotate(0deg)':'translate(22px, 16px) rotate(9deg)',opacity:isGhee?'.98':'0'});
   for(const el of stage.querySelectorAll('.ingredient-drop'))plan(el,{transform:isGhee?'translate(9px, -9px)':'translate(0px, 0px)',opacity:isGhee?'.25':'.65'});
   plan(root.querySelector('.selection-surface'),{transform:isGhee?'translateX(100%)':'translateX(0%)'});
   running.current.forEach(a=>a.cancel());running.current=[];
   stage.dataset.exchange=moving?'moving':'settled';stage.setAttribute('aria-busy',String(moving));
   const start=document.timeline.currentTime;
   for(const {el,from,target,settle} of plans){
    Object.assign(el.style,target);
    if(!moving)continue;
    let frames=[from,target];
    if(settle){const scale=Number(target.transform.match(/scale\(([^)]+)\)/)[1]);frames=[from,{transform:target.transform.replace('scale('+scale+')','scale('+(scale*1.009)+')'),offset:.84},target];el.dataset.from=from.transform;el.dataset.to=target.transform;}
    const animation=el.animate(frames,{duration:DURATION,easing:EASING});animation.startTime=start;running.current.push(animation);
   }
   const complete=()=>{if(token!==revision.current)return;cancelAnimationFrame(monitorFrame.current);monitorFrame.current=0;stage.dataset.exchange='settled';stage.setAttribute('aria-busy','false');settleRef.current(ids[0]);};
   if(moving){
    const monitor=()=>{if(token!==revision.current)return;const target=stage.querySelector('[data-product="'+ids[0]+'"]'),width=target.getBoundingClientRect().width,dominant=[...stage.querySelectorAll('.showcase-jar')].every(el=>el===target||el.getBoundingClientRect().width<=width);if(dominant){flushSync(()=>settleRef.current(ids[0]));monitorFrame.current=0;}else monitorFrame.current=requestAnimationFrame(monitor);};monitorFrame.current=requestAnimationFrame(monitor);
    Promise.all(running.current.map(a=>a.finished)).then(complete).catch(()=>{});
   }else complete();
   last.current=[...ids];
  }
  layoutRef.current=layout;layout(false);
  const observer=new ResizeObserver(()=>{const r=stage.getBoundingClientRect();if(r.width+':'+r.height!==measured)layout(false);});observer.observe(stage);
  const motion=matchMedia('(prefers-reduced-motion: reduce)'),change=()=>layout(false);motion.addEventListener('change',change);
  return()=>{layoutRef.current=null;observer.disconnect();motion.removeEventListener('change',change);revision.current++;cancelAnimationFrame(monitorFrame.current);running.current.forEach(a=>a.cancel());};
 },[stageRef]);
 useLayoutEffect(()=>{layoutRef.current?.(true);},[order]);
}
