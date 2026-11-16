import {useEffect} from 'react';
export function usePublicMotion(ref){
 useEffect(()=>{
  const root=ref.current,motion=matchMedia('(prefers-reduced-motion: reduce)');let frame=0,reveals=[];
  const paint=()=>{frame=0;root.querySelectorAll('.golden-wave').forEach(wave=>{const rect=wave.getBoundingClientRect(),progress=Math.max(0,Math.min(1,(innerHeight-rect.top)/(innerHeight+rect.height)));wave.style.setProperty('--wave-x',`${motion.matches?0:(progress-.5)*36}px`);wave.style.setProperty('--wave-rise',`${motion.matches?0:(progress-.5)*12}px`);});};
  const schedule=()=>{if(!frame)frame=requestAnimationFrame(paint);};
  const mediaChange=()=>{if(motion.matches){reveals.forEach(animation=>animation.cancel());reveals=[];}schedule();};
  const observer=new IntersectionObserver(entries=>{
   for(const entry of entries){
    if(!entry.isIntersecting)continue;observer.unobserve(entry.target);if(motion.matches)continue;
    const cards=entry.target.querySelectorAll('[data-reveal-card]');
    if(cards.length)cards.forEach((el,index)=>reveals.push(el.animate([{opacity:0,transform:'translateY(22px)'},{opacity:1,transform:'translateY(0)'}],{duration:520,delay:index*90,easing:'cubic-bezier(.2,.7,.2,1)'})));
    else reveals.push(entry.target.animate([{transform:'translateX(45px)'},{transform:'translateX(0)'}],{duration:650,easing:'cubic-bezier(.2,.7,.2,1)'}));
   }
  },{threshold:.12});
  root.querySelectorAll('[data-reveal]').forEach(el=>observer.observe(el));paint();addEventListener('scroll',schedule,{passive:true});addEventListener('resize',schedule);motion.addEventListener('change',mediaChange);
  return()=>{observer.disconnect();cancelAnimationFrame(frame);reveals.forEach(animation=>animation.cancel());removeEventListener('scroll',schedule);removeEventListener('resize',schedule);motion.removeEventListener('change',mediaChange);};
 },[ref]);
}
