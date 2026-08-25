(() => {
    const SEMANTIC = new Set(['a', 'button', 'input', 'select', 'textarea', 'label', 'form',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'img', 'svg', 'nav', 'main', 'header', 'footer', 'aside', 'dialog', 'details', 'summary',
        'li', 'p', 'ul', 'ol', 'dl', 'turbo-frame']);
    const ATTRIBUTES = ['href', 'src', 'name', 'type', 'placeholder', 'alt', 'title', 'role',
        'aria-label', 'data-controller', 'for', 'action'];
    const cap = (value, limit) => value.length > limit ? value.slice(0, limit) : value;

    const selectorFor = (element) => {
        const parts = [];
        for (let node = element; node && node !== document.body; node = node.parentElement) {
            if (node.id) {
                parts.unshift('#' + CSS.escape(node.id));
                return parts.join(' > ');
            }
            const tag = node.localName;
            const peers = node.parentElement
                ? [...node.parentElement.children].filter((child) => child.localName === tag)
                : [];
            parts.unshift(peers.length > 1 ? `${tag}:nth-of-type(${peers.indexOf(node) + 1})` : tag);
        }
        parts.unshift('body');
        return parts.join(' > ');
    };

    const directText = (element) => [...element.childNodes]
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .map((node) => node.textContent)
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim();

    const elements = [];
    for (const element of document.body.querySelectorAll('*')) {
        const rect = element.getBoundingClientRect();
        if (rect.width < 2 || rect.height < 2) {
            continue;
        }
        const tag = element.localName;
        const own = directText(element);
        if (!element.id && !SEMANTIC.has(tag) && '' === own
            && !element.hasAttribute('data-controller') && !element.hasAttribute('role')) {
            continue;
        }
        const attributes = {};
        for (const name of ATTRIBUTES) {
            const value = element.getAttribute(name);
            if (value) {
                attributes[name] = cap(value, 120);
            }
        }
        // SVGElement has no innerText property.
        const inner = (element.innerText ?? '').replace(/\s+/g, ' ').trim();
        elements.push({
            selector: selectorFor(element),
            tag,
            x: Math.round(rect.x + window.scrollX),
            y: Math.round(rect.y + window.scrollY),
            width: Math.round(rect.width),
            height: Math.round(rect.height),
            text: inner.length > 0 && inner.length <= 200 ? cap(inner, 120) : (own ? cap(own, 120) : null),
            classes: element.getAttribute('class') ? cap(element.getAttribute('class'), 200) : null,
            attributes,
        });
    }

    return JSON.stringify({
        url: location.href,
        title: document.title,
        elements,
    });
})()
