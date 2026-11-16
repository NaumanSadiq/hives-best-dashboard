import { productById } from './products';
export default function ProductPhoto({product = productById('1kg'), priority = false, eager = false, decorative = false}) {
    return <div className="jar-visual" data-product={product.id}><img src={product.image} srcSet={product.srcSet} sizes="(max-width: 699px) 600px, (max-width: 1100px) 800px, 1000px" width="1254" height="1254" alt={decorative ? '' : `${product.name}, ${product.weight}${product.volume ? ', '+product.volume : ''}, original Hive’s Best label and black lid`} loading={priority || eager ? 'eager' : 'lazy'} fetchPriority={priority ? 'high' : 'auto'} decoding="async" /></div>;
}
