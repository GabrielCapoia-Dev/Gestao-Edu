<div
    x-data
    x-on:open-url.window="window.open($event.detail.url, '_blank')"
    x-on:download-url.window="window.location.assign($event.detail.url)"
></div>
