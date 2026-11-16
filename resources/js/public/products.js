export const products = [
    { id: '500g', family: 'honey', theme: 'honey-soft', name: 'Raw Wild Dwarf Honey', shortName: 'Raw Honey', weight: '500 g', volume: 'approx. 350 ml', description: 'A little wild sweetness for the everyday.', source: 'honey-500g-square.png', image: '/images/public/honey-500g-960.webp', srcSet: '/images/public/honey-500g-640.webp 640w, /images/public/honey-500g-960.webp 960w, /images/public/honey-500g-1254.webp 1254w' },
    { id: '1kg', family: 'honey', theme: 'honey', name: 'Raw Wild Dwarf Honey', shortName: 'Raw Honey', weight: '1 kg', volume: 'approx. 700 ml', description: 'More goodness to bring to the table.', source: 'honey-1kg-square.jfif', image: '/images/public/honey-1kg-960.webp', srcSet: '/images/public/honey-1kg-640.webp 640w, /images/public/honey-1kg-960.webp 960w, /images/public/honey-1kg-1254.webp 1254w' },
    { id: 'ghee', family: 'ghee', theme: 'ghee', name: 'Pure Desi Ghee', shortName: 'Desi Ghee', weight: '1 kg', volume: null, description: 'Golden ghee, made from cow’s milk cream.', source: 'desi-ghee-1kg.png', image: '/images/public/ghee-1kg-960.webp', srcSet: '/images/public/ghee-1kg-640.webp 640w, /images/public/ghee-1kg-960.webp 960w, /images/public/ghee-1kg-1254.webp 1254w' },
];
export const productById = id => products.find(product => product.id === id);
export const instagram = 'https://www.instagram.com/hives_best/';
export const enquiryTarget = product => `#product-${product.id}`;
