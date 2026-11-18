import {useCallback,useEffect,useRef,useState} from 'react';
import {createRoot} from 'react-dom/client';
import JarPreview from './JarPreview';
import ProductPhoto from './ProductPhoto';
import {products,heroProducts,productById,instagram,enquiryTarget} from './products';
import {useProductExchange} from './useProductExchange';
import {usePublicMotion} from './usePublicMotion';
import './welcome.css';

function Arrow({back=false}){return <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" aria-hidden="true" style={back?{rotate:'180deg'}:undefined}><path d="M4 12h15m-6-6 6 6-6 6"/></svg>;}
function Contact({product,children='Get in touch',className='button'}){return <a className={className} href={instagram} target="_blank" rel="noopener noreferrer" aria-label={product?`Enquire on Instagram about ${product.name}, ${product.weight}${product.volume?', '+product.volume:''}`:undefined}>{children}<Arrow/></a>;}
function Brand(){return <a className="brand" href="#top" aria-label="Hive’s Best home"><img src="/images/public/wordmark.webp" width="420" height="119" alt="Hive’s Best Honey"/></a>;}
function Header() {
    const [open, setOpen] = useState(false);
    const toggle = useRef(null);
    useEffect(() => {
        if (!open) return;
        const escape = event => {
            if (event.key === 'Escape') {
                setOpen(false);
                toggle.current.focus();
            }
        };
        document.addEventListener('keydown', escape);
        return () => document.removeEventListener('keydown', escape);
    }, [open]);
    return <header className="public-header layout">
        <Brand/>
        <button type="button" className="menu-toggle" ref={toggle} aria-expanded={open}
            aria-controls="public-nav" aria-label={open ? 'Close navigation' : 'Open navigation'}
            onClick={() => setOpen(value => !value)}>
            <span className="burger" aria-hidden="true"><i/><i/><i/></span>
        </button>
        <nav id="public-nav" className={open ? 'is-open' : ''} aria-label="Main navigation"
            onClick={event => {if (event.target.closest('a')) setOpen(false);}}>
            <a href="#products">Our products</a><a href="#faq">FAQs</a><Contact className="nav-contact">Say hello</Contact>
        </nav>
    </header>;
}
function Wave({closing=false}){return <div className={`golden-wave ${closing?'closing-wave':''}`} aria-hidden="true"><svg viewBox="0 0 1440 64" preserveAspectRatio="none"><path className="wave-back" d={closing?'M0 28Q0 28,30 30.7145404980717Q60 33.4290809961434,90 30.64097970427303Q120 27.852878412402664,150 27.262804690909014Q180 26.672730969415362,210 29.79285577102311Q240 32.91298057263086,270 32.09337695974364Q300 31.273773346856412,330 29.424332780340173Q360 27.57489221382393,390 30.73767859863711Q420 33.90046498345029,450 35.366899176907836Q480 36.83333337036538,510 33.054952569186554Q540 29.276571768007727,570 28.07748602127054Q600 26.878400274533355,630 27.727883727210276Q660 28.577367179887197,690 24.59623964109413Q720 20.61511210230106,750 17.036275739661487Q780 13.457439377021913,810 15.681312574921247Q840 17.90518577282058,870 18.672468213141975Q900 19.43975065346337,930 17.075932495415252Q960 14.712114337367137,990 16.667014685697932Q1020 18.621915034028728,1050 21.77904368075197Q1080 24.936172327475205,1110 22.657034098098272Q1140 20.377895868721335,1170 18.95474334416579Q1200 17.531590819610248,1230 20.78566466216435Q1260 24.039738504718457,1290 24.509548922180944Q1320 24.97935933964343,1350 23.38207664213129Q1380 21.78479394461915,1410 25.418388981128547L1440 29.051984017637945V64H0Z':'M0 28Q0 28,15 29.570731597652355Q30 31.141463195304713,45 27.305075056686192Q60 23.468686918067675,75 22.013393195639498Q90 20.55809947321132,105 23.068327587834023Q120 25.578555702456725,135 24.455312560506215Q150 23.332069418555704,165 21.5106041008476Q180 19.6891387831395,195 23.209178918688657Q210 26.72921905423781,225 28.852350436180586Q240 30.975481818123367,255 28.098399384073332Q270 25.2213169500233,285 25.0928612954224Q300 24.964405640821496,315 26.964440183828003Q330 28.96447472683451,345 26.11773416745072Q360 23.27099360806693,375 20.71564490605835Q390 18.16029620404977,405 21.211283987464764Q420 24.262271770879757,435 25.59122152240536Q450 26.92017127393096,465 24.80566705244142Q480 22.691162830951882,495 24.5622029736529Q510 26.433243116353925,525 29.180340769630654Q540 31.927438422907382,555 28.946341022679213Q570 25.965243622451045,585 23.606825938685674Q600 21.248408254920307,615 23.4120170467392Q630 25.575625838558096,645 24.890838502575093Q660 24.206051166592093,675 21.486461067975444Q690 18.766870969358795,705 21.40417537221175Q720 24.041479775064705,735 27.163080584467714Q750 30.284681393870724,765 28.149038822719504Q780 26.01339625156828,795 25.420566722560427Q810 24.827737193552572,825 27.34038783037619Q840 29.85303846719981,855 27.834722915341544Q870 25.816407363483275,885 22.25849298539974Q900 18.700578607316203,915 20.711236079014284Q930 22.721893550712366,945 24.551083521949316Q960 26.380273493186266,975 24.204756193175644Q990 22.029238893165022,1005 23.185442857293285Q1020 24.34164682142155,1035 27.988186710411206Q1050 31.63472659940086,1065 29.869140483602862Q1080 28.103554367804865,1095 25.20931558541273Q1110 22.315076803020595,1125 24.07267882568257Q1140 25.830280848344543,1155 25.713914574286335Q1170 25.597548300228127,1185 22.19152157065362Q1200 18.785494841079114,1215 20.166804293208553Q1230 21.548113745337993,1245 25.224491930428346Q1260 28.9008701155187,1275 27.600053856573766Q1290 26.29923759762883,1305 25.29173626648206Q1320 24.284234935335295,1335 27.17299037960967Q1350 30.061745823884042,1365 29.211485098162818Q1380 28.361224372441598,1395 24.265171045713178Q1410 20.169117718984758,1425 21.00380781323414L1440 21.838497907483525V64H0Z'} /><path className="wave-front" d={closing?'M0 40Q0 40,30 42.7145404980717Q60 45.42908099614339,90 42.640979704273036Q120 39.852878412402674,150 39.26280469090902Q180 38.67273096941536,210 41.79285577102311Q240 44.912980572630865,270 44.09337695974364Q300 43.273773346856416,330 41.42433278034017Q360 39.57489221382393,390 42.73767859863711Q420 45.90046498345029,450 47.366899176907836Q480 48.833333370365374,510 45.054952569186554Q540 41.27657176800773,570 40.07748602127054Q600 38.87840027453335,630 39.72788372721027Q660 40.5773671798872,690 36.59623964109413Q720 32.61511210230106,750 29.036275739661487Q780 25.457439377021913,810 27.681312574921243Q840 29.905185772820573,870 30.672468213141972Q900 31.43975065346337,930 29.075932495415252Q960 26.712114337367137,990 28.66701468569793Q1020 30.621915034028724,1050 33.77904368075197Q1080 36.936172327475205,1110 34.657034098098265Q1140 32.37789586872133,1170 30.95474334416579Q1200 29.531590819610248,1230 32.78566466216435Q1260 36.03973850471846,1290 36.50954892218095Q1320 36.97935933964344,1350 35.382076642131295Q1380 33.78479394461915,1410 37.418388981128544L1440 41.05198401763794V64H0Z':'M0 40Q0 40,15 41.570731597652355Q30 43.14146319530471,45 39.30507505668619Q60 35.46868691806768,75 34.0133931956395Q90 32.55809947321132,105 35.06832758783403Q120 37.57855570245673,135 36.455312560506215Q150 35.332069418555704,165 33.5106041008476Q180 31.6891387831395,195 35.20917891868866Q210 38.72921905423781,225 40.852350436180586Q240 42.97548181812336,255 40.09839938407333Q270 37.2213169500233,285 37.092861295422395Q300 36.96440564082149,315 38.964440183828Q330 40.96447472683451,345 38.11773416745072Q360 35.27099360806693,375 32.71564490605835Q390 30.16029620404977,405 33.21128398746476Q420 36.26227177087975,435 37.591221522405355Q450 38.92017127393096,465 36.80566705244142Q480 34.69116283095188,495 36.5622029736529Q510 38.43324311635392,525 41.180340769630654Q540 43.927438422907386,555 40.94634102267921Q570 37.96524362245104,585 35.606825938685674Q600 33.24840825492031,615 35.4120170467392Q630 37.575625838558096,645 36.89083850257509Q660 36.2060511665921,675 33.48646106797545Q690 30.766870969358795,705 33.404175372211746Q720 36.0414797750647,735 39.163080584467714Q750 42.28468139387073,765 40.149038822719504Q780 38.013396251568274,795 37.42056672256042Q810 36.82773719355257,825 39.34038783037619Q840 41.85303846719981,855 39.834722915341544Q870 37.81640736348327,885 34.25849298539974Q900 30.700578607316203,915 32.711236079014284Q930 34.721893550712366,945 36.551083521949316Q960 38.380273493186266,975 36.20475619317564Q990 34.02923889316502,1005 35.18544285729328Q1020 36.34164682142155,1035 39.9881867104112Q1050 43.63472659940086,1065 41.86914048360286Q1080 40.10355436780486,1095 37.209315585412725Q1110 34.315076803020595,1125 36.07267882568257Q1140 37.83028084834454,1155 37.713914574286335Q1170 37.59754830022813,1185 34.19152157065362Q1200 30.785494841079114,1215 32.16680429320856Q1230 33.54811374533799,1245 37.22449193042834Q1260 40.9008701155187,1275 39.600053856573766Q1290 38.29923759762883,1305 37.29173626648206Q1320 36.2842349353353,1335 39.17299037960967Q1350 42.06174582388404,1365 41.21148509816282Q1380 40.3612243724416,1395 36.265171045713174Q1410 32.16911771898476,1425 33.00380781323415L1440 33.83849790748353V64H0Z'} /></svg></div>;}
function Ingredient(){return <div className="ingredient-layer" aria-hidden="true"><img className="comb-photo" src="/images/public/honeycomb-crescent.webp" width="480" height="720" alt="" decoding="async"/><img className="ghee-photo" src="/images/public/ghee-curl.webp" width="480" height="720" alt="" decoding="async"/><span className="ingredient-drop drop-one"/><span className="ingredient-drop drop-two"/></div>;}
function Hero({order,onSelect,featured,onSettled}){
 const stage=useRef(null),requested=order[0],shown=productById(featured);
 useProductExchange(stage,order,onSettled);
 const keyboard=e=>{if(!['ArrowLeft','ArrowRight','Home','End'].includes(e.key))return;e.preventDefault();const index=heroProducts.findIndex(p=>p.id===requested),next=e.key==='Home'?0:e.key==='End'?heroProducts.length-1:(index+(e.key==='ArrowRight'?1:-1)+heroProducts.length)%heroProducts.length;onSelect(heroProducts[next].id);e.currentTarget.querySelector(`[data-choice="${heroProducts[next].id}"]`).focus();};
 return <section className="product-hero layout" id="top" data-selected={requested} data-featured={featured} aria-labelledby="hero-title"><div className="hero-copy"><p className="eyebrow">Hive’s Best · Based in Jhelum</p><h1 id="hero-title">Raw honey.<br/><em>Pure desi ghee.</em></h1><fieldset className="product-choices" onKeyDown={keyboard}><legend className="sr-only">Featured product</legend><div><i className="selection-surface" aria-hidden="true"/>{heroProducts.map(p=><button type="button" key={p.id} data-choice={p.id} aria-pressed={requested===p.id} onClick={()=>onSelect(p.id)}><i className={"swatch "+p.family}/><span>{p.family==="honey"?"Raw Honey":"Desi Ghee"}</span></button>)}</div></fieldset></div>
 <div className="hero-showcase"><div className="product-stage" ref={stage} data-exchange="settled" aria-label="Interactive product showcase">
  <div className="stage-glow" aria-hidden="true"/><Ingredient/>
  {[0,1].map(slot=><span className="jar-slot" data-slot={slot} key={slot} aria-hidden="true"/>)}
  <span className="stage-shadow" aria-hidden="true"/>
  {heroProducts.map(p=><div className="showcase-jar" key={p.id} data-product={p.id} data-slot={heroProducts.findIndex(product=>product.id===p.id)} aria-hidden="true"><ProductPhoto product={p} priority={p.id==='1kg'} eager decorative hero/></div>)}
  {[1].map(slot=>{const p=productById(order[slot]);return <button type="button" className={`preview-label preview-${slot}`} key={slot} data-preview={p.id} aria-label={`Feature ${p.name}, ${p.weight}`} onClick={()=>onSelect(p.id)}><span>{p.shortName}</span><strong>{p.weight}<span aria-hidden="true">↗</span></strong></button>;})}
 </div><div className="featured-meta" aria-live="polite" aria-atomic="true"><div><span>{shown.name}</span><strong>{shown.weight}{shown.volume&&<small> · {shown.volume}</small>}</strong></div><a href={enquiryTarget(shown)} aria-label={`View ${shown.name}, ${shown.weight} in the collection`}>Meet this jar<Arrow/></a></div></div></section>;
}
const priceFormat = new Intl.NumberFormat('en-PK');

function Collection() {
    return <section className="catalogue layout" id="products" aria-labelledby="collection-title">
        <h2 id="collection-title">Our collection</h2>
        <ul className="catalogue-rail" aria-label="Products">
            {products.map(product => <li key={product.id}>
                <article className="catalogue-card" id={'product-' + product.id}>
                    <div className={'family-' + product.family}><JarPreview product={product}/></div>
                    <div className="catalogue-body">
                        <h3>{product.name}</h3>
                        <p className="pack-size">{product.weight}{product.volume && <span> &middot; {product.volume}</span>}</p>
                        <p className="product-price">PKR {priceFormat.format(product.price)}</p>
                    </div>
                </article>
            </li>)}
        </ul>
    </section>;
}
const questions=[
 ['What makes this honey special?','Our honey is raw wild dwarf honeybee honey. Hive’s Best is based in Jhelum, sharing a little goodness from these small, remarkable bees.'],
 ['Which jar sizes can I choose?',`Raw Wild Dwarf Honey comes in ${products[0].weight} (${products[0].volume}) and ${products[1].weight} (${products[1].volume}). Pure Desi Ghee comes in a ${products[2].weight} jar.`],
 ['What is the desi ghee made from?','Our Pure Desi Ghee is made from cow’s milk cream, as shown on the supplied product label.'],
 ['How do I enquire or place an order?','Message @hives_best on Instagram with the product and jar size you have in mind. We’ll help with current details before you decide.'],
];
function FAQ(){const[open,setOpen]=useState(null);return <section className="faq-section" id="faq" aria-labelledby="faq-title"><div className="faq-layout layout"><figure className="faq-art"><img src="/images/public/campaign.webp" width="1254" height="1254" loading="lazy" alt="Open jars of Hive’s Best honey and desi ghee with honeycomb and golden ribbons."/></figure><div className="faq-list"><h2 id="faq-title">Frequently asked questions</h2>{questions.map(([q,a],index)=><div className={`faq-item ${open===index?'is-open':''}`} key={q}><h3><button type="button" id={`faq-question-${index}`} aria-expanded={open===index} aria-controls={`faq-answer-${index}`} onClick={()=>setOpen(open===index?null:index)}>{q}<span className="faq-icon" aria-hidden="true">{open===index?'−':'+'}</span></button></h3><div className="faq-answer" id={`faq-answer-${index}`} role="region" aria-labelledby={`faq-question-${index}`} aria-hidden={open!==index} inert={open!==index}><div><p>{a}</p></div></div></div>)}</div></div></section>;}
function Closing(){return <section className="closing" id="contact" aria-labelledby="contact-title"><div className="layout closing-inner"><div><h2 id="contact-title">Contact Hive’s Best</h2></div><a className="button send-link" href={instagram} target="_blank" rel="noopener noreferrer">Message @hives_best<span className="send-icon" aria-hidden="true"><Arrow/><Arrow/></span></a></div><Wave closing/></section>;}
function Footer(){return <footer className="public-footer"><div className="layout footer-top"><Brand/><p>Raw honey. Pure desi ghee.<br/>Based in Jhelum, Pakistan.</p><Contact className="footer-contact">@hives_best</Contact></div><div className="layout footer-bottom"><small>© {new Date().getFullYear()} Hive’s Best</small><a href="#top">Back to the top ↑</a></div></footer>;}
function App(){const[order,setOrder]=useState(['1kg','ghee']),[featured,setFeatured]=useState('1kg'),ref=useRef(null);usePublicMotion(ref);const settled=useCallback(id=>setFeatured(id),[]);const select=useCallback(id=>setOrder(previous=>{const index=previous.indexOf(id);if(index<=0)return previous;const next=[...previous];[next[0],next[index]]=[next[index],next[0]];return next;}),[]);
 useEffect(()=>{const jump=()=>{try{const id=decodeURIComponent(location.hash.slice(1));if(id)document.fonts.ready.then(()=>requestAnimationFrame(()=>document.getElementById(id)?.scrollIntoView({behavior:'instant'})));}catch{}};jump();addEventListener('hashchange',jump);return()=>removeEventListener('hashchange',jump);},[]);
 return <div className="golden-page" ref={ref} data-theme={productById(order[0]).theme}><a className="skip-link" href="#main">Skip to content</a><div className="header-zone"><Header/></div><main id="main" tabIndex="-1"><div className="hero-zone"><Hero order={order} onSelect={select} featured={featured} onSettled={settled}/><Wave/></div><Collection/><FAQ/><Closing/></main><Footer/></div>;
}
createRoot(document.getElementById('welcome-root')).render(<App/>);
