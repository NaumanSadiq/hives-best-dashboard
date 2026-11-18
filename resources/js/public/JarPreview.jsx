import {useLayoutEffect, useRef, useState} from 'react';
import ProductPhoto from './ProductPhoto';

export default function JarPreview({product}) {
    const frame = useRef(null);
    const tracks = useRef([]);
    const touch = useRef(false);
    const desired = useRef(false);
    const beforePress = useRef(false);
    const [open, setOpen] = useState(false);

    const [arrival, setArrival] = useState('waiting');
    useLayoutEffect(() => {
        const button = frame.current;
        const mobile = window.matchMedia('(max-width:699px)');
        const observe = () => {
            observer.disconnect();
            observer.observe(mobile.matches ? button : button.closest('.catalogue-rail'));
        };
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                setArrival(state => state === 'waiting' ? 'landing' : state);
                observer.disconnect();
            }
        }, {threshold:0.16});
        observe();
        mobile.addEventListener('change', observe);
        return () => {observer.disconnect(); mobile.removeEventListener('change', observe);};
    }, []);

    function preview(next) {
        if (next) setArrival('landed');
        desired.current = next;
        setOpen(next);
        const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
        for (const animation of tracks.current) {
            if (reduced) {
                animation.pause();
                animation.currentTime = next ? 820 : 0;
            } else {
                animation.updatePlaybackRate(next ? 1 : -1);
                animation.play();
            }
        }
    }
    useLayoutEffect(() => {
        function track(selector, frames) {
            const animation = frame.current.querySelector(selector).animate(frames, {
                duration: 820, fill: 'both', easing: 'ease-in-out'
            });
            animation.pause();
            animation.currentTime = 0;
            tracks.current.push(animation);
        }
        track('.photo-stack', [
            {transform:'scale(1) rotate(0deg)'},
            {transform:'scale(1.04) rotate(-1.5deg)',offset:.13},
            {transform:'scale(1.06) rotate(1deg)',offset:.23},
            {transform:'scale(1.06) rotate(0deg)',offset:.34},
            {transform:'scale(1.06) rotate(0deg)'}
        ]);
        track('.jar-cap', [
            {transform:'none'}, {transform:'none',offset:.21}, {transform:'translateY(-1%) rotate(-2deg)',offset:.32},
            {transform:'translate(9%,-18%) rotate(5deg)',offset:.58},
            {transform:'translate(12%,-20%) rotate(7deg)'}
        ]);
        track('.jar-neck',[{opacity:0},{opacity:0,offset:.21},{opacity:1,offset:.30},{opacity:1}]);
        track('.jar-stream',[{transform:'scaleY(0)'},{transform:'scaleY(0)',offset:.45},{transform:'scaleY(1)'}]);
        const media = matchMedia('(prefers-reduced-motion: reduce)');
        const change = () => preview(desired.current);
        media.addEventListener('change',change);
        return () => { media.removeEventListener('change',change); tracks.current.forEach(a=>a.cancel()); tracks.current=[]; };
    }, []);
    return <button type="button" ref={frame} className="jar-preview" data-arrival={arrival} aria-pressed={open}
        aria-label={'Preview '+product.name+', '+product.weight}
        onPointerEnter={e=>{touch.current=e.pointerType==='touch';if(!touch.current && matchMedia('(hover: hover)').matches)preview(true);}}
        onPointerLeave={e=>{if(e.pointerType!=='touch')preview(false);}}
        onFocus={e=>{if(e.target.matches(':focus-visible'))preview(true);}}
        onBlur={()=>preview(false)}
        onPointerDown={e=>{touch.current=e.pointerType==='touch';beforePress.current=desired.current;}}
        onClick={e=>preview(e.detail && touch.current ? !beforePress.current : !desired.current)}>
        <span className="jar-landing"><span className="photo-stack">
            <span className="jar-base"><ProductPhoto product={product} decorative/></span>
            <img className="jar-neck" src={'/images/public/'+product.family+'-neck.webp'} alt=""/>
            <img className="jar-stream" src={'/images/public/'+product.family+'-ribbon.webp'} alt=""/>
            <span className="jar-cap"><ProductPhoto product={product} decorative/></span>

        </span></span><span className="shelf-shadow" aria-hidden="true"/>
    </button>;
}
