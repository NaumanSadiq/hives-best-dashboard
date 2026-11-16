import {useLayoutEffect,useRef} from 'react';
const BASE=240;
export function useProductExchange(stageRef,order,onSettled){
 const running=useRef([]),last=useRef(null),revision=useRef(0),monitorFrame=useRef(0),orderRef=useRef(order),settleRef=useRef(onSettled),layoutRef=useRef(null);
 orderRef.current=order;settleRef.current=onSettled;
 useLayoutEffect(()=>{
  const stage=stageRef.current;if(!stage)return;let measured='';
  function layout(animate){
   const stageRect=stage.getBoundingClientRect(),ids=orderRef.current,token=++revision.current;
   measured=`${stageRect.width}:${stageRect.height}`;cancelAnimationFrame(monitorFrame.current);monitorFrame.current=0;
   const snapshots=[...stage.querySelectorAll('.showcase-jar')].map(el=>({el,transform:getComputedStyle(el).transform}));
   running.current.forEach(a=>a.cancel());running.current=[];
   const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
   const changed=last.current&&ids.some((id,index)=>id!==last.current[index]);
   const shouldAnimate=animate&&!reduced&&changed;
   stage.dataset.exchange=shouldAnimate?'moving':'settled';stage.setAttribute('aria-busy',String(Boolean(shouldAnimate)));
   let incoming;
   for(const {el,transform} of snapshots){
    const index=ids.indexOf(el.dataset.product),slot=stage.querySelector(`[data-slot="${index}"]`).getBoundingClientRect();
    const x=slot.left-stageRect.left,y=slot.top-stageRect.top,scale=slot.width/BASE;
    const end=`translate3d(${x}px,${y}px,0) scale(${scale})`;
    el.style.transform=end;el.style.zIndex=index===0?'4':'2';el.dataset.slot=String(index);
    if(shouldAnimate){
     const frames=index===0?[{transform},{transform:`translate3d(${x-3}px,${y-3}px,0) scale(${scale*1.012})`,offset:.82},{transform:end}]:[{transform},{transform:end}];
     const animation=el.animate(frames,{duration:560,easing:'cubic-bezier(.22,.7,.2,1)'});running.current.push(animation);if(index===0)incoming=animation;
     el.dataset.from=transform;el.dataset.to=end;
    }
   }
   for(const [selector,frames,duration]of[
    ['.stage-shadow',[{transform:'translateX(16px) scale(.85)',opacity:.1},{transform:'translateX(0) scale(1)',opacity:.24}],560],
    ['.ingredient-layer',[{transform:'translateX(-20px) rotate(-4deg)'},{transform:'translateX(0) rotate(0deg)'}],700],
    ['.stage-glow',[{transform:'translateX(30px) scale(.92)'},{transform:'translateX(0) scale(1)'}],760],
   ]){const el=stage.querySelector(selector);if(shouldAnimate)running.current.push(el.animate(frames,{duration,easing:'cubic-bezier(.2,.7,.2,1)'}));}
   // Commit metadata as soon as the incoming jar becomes visually dominant.
   // Waiting for the final settle leaves an already-large ghee jar labelled honey.
   if(incoming){const monitor=()=>{if(token!==revision.current)return;const target=stage.querySelector('[data-product="'+ids[0]+'"]'),width=target.getBoundingClientRect().width,others=[...stage.querySelectorAll('.showcase-jar')].filter(el=>el!==target);if(others.every(el=>el.getBoundingClientRect().width<=width)){settleRef.current(ids[0]);monitorFrame.current=0;}else monitorFrame.current=requestAnimationFrame(monitor);};monitorFrame.current=requestAnimationFrame(monitor);}
   const complete=()=>{if(token!==revision.current)return;cancelAnimationFrame(monitorFrame.current);monitorFrame.current=0;stage.dataset.exchange='settled';stage.setAttribute('aria-busy','false');settleRef.current(ids[0]);};
   if(incoming)incoming.finished.then(complete).catch(()=>{});else complete();
   last.current=[...ids];
  }
  layoutRef.current=layout;layout(false);
  const observer=new ResizeObserver(()=>{const r=stage.getBoundingClientRect();if(`${r.width}:${r.height}`!==measured)layout(false);});observer.observe(stage);
  const motion=matchMedia('(prefers-reduced-motion: reduce)'),change=()=>layout(false);motion.addEventListener('change',change);
  return()=>{layoutRef.current=null;observer.disconnect();motion.removeEventListener('change',change);revision.current++;cancelAnimationFrame(monitorFrame.current);running.current.forEach(a=>a.cancel());};
 },[stageRef]);
 useLayoutEffect(()=>{layoutRef.current?.(true);},[order]);
}
