import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle.js';
import 'swagger-ui-dist/swagger-ui.css';

window.addEventListener('DOMContentLoaded', () => {
    SwaggerUIBundle({
        url: '/open-api/docs.json',
        dom_id: '#swagger-ui',
        deepLinking: true,
    });
});
