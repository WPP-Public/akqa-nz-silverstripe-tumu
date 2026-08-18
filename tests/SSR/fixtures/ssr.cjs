'use strict';

const { createElement } = require('react');
const { renderToString } = require('react-dom/server');

function Banner({ title, items }) {
    const heading = createElement('h1', null, title);
    const list =
        Array.isArray(items) && items.length > 0
            ? createElement(
                  'ul',
                  null,
                  items.map((item, index) =>
                      createElement('li', { key: String(index) }, item)
                  )
              )
            : null;

    return createElement('section', { className: 'banner' }, heading, list);
}

function Broken() {
    throw new Error('intentional render failure');
}

const registry = {
    Banner,
    Broken,
};

function readStdin() {
    return new Promise((resolve, reject) => {
        const chunks = [];
        process.stdin.on('data', (chunk) => chunks.push(chunk));
        process.stdin.on('end', () =>
            resolve(Buffer.concat(chunks).toString('utf8'))
        );
        process.stdin.on('error', reject);
    });
}

readStdin()
    .then((raw) => {
        const { component, props } = JSON.parse(raw);
        const Component = registry[component];
        if (!Component) {
            process.stderr.write(`Unknown React component: ${component}\n`);
            process.exit(1);
        }
        process.stdout.write(renderToString(createElement(Component, props)));
    })
    .catch((error) => {
        process.stderr.write(String(error && error.stack ? error.stack : error));
        process.exit(1);
    });
