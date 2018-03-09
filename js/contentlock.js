(function ($) {
    let ids = [];
    let locks = [];

    function loadContentLocks() {
        $('[data-contentlock]').each(function (i, el) {
            const post = $(el).data('post');
            const id = $(el).data('contentlock');
            const time = $(el).data('locktime');
            const release = (new Date().getTime() / 1000) + parseInt(time);

            // only store unique locks
            let index = ids.indexOf(id);
            if (index === -1) {
                ids.push(id);
                locks.push({
                    post: post,
                    id: id,
                    containers: [el],
                    release: release
                });
            } else {
                locks[index].containers.push(el);
            }
        });
    }

    function processLockTick(lock) {
        const seconds = new Date().getTime() / 1000;
        const remainder = parseInt(lock.release - seconds);

        if (remainder >= 0) {
            // update containers
            const chunks = parseRemaining(remainder);
            lock.containers.forEach(container => {
                const $el = $(container);
                $el.find('span.hours').html(chunks[0]);
                $el.find('span.minutes').html(chunks[1]);
                $el.find('span.seconds').html(chunks[2]);
            });
            return lock;
        } else {
            // fetch unlocked content and null out lock
            let data = {
                action: ajax_object.action, // provided by plugin
                post: lock.post,
                id: lock.id
            };

            $.post(ajax_object.ajax_url, data, function (response) {
                let res = JSON.parse(response);
                let code = res.status;

                if (code == 204) { // content unchanged (still locked)
                    // add back to locks array for next fetch
                    locks.push(lock);
                } else if (code == 200) { // retrieved the content, so replace
                    lock.containers.forEach(container => {
                        console.info('replacing', container);
                        $(container).replaceWith(res.data);
                    });
                }
            });
            return null;
        }
    }

    function parseRemaining(seconds) {
        let hours = Math.floor(seconds / 3600);
        let min = Math.floor((seconds % 3600) / 60);
        let sec = (seconds % 3600) % 60;
        // zero pad
        return [hours, min, sec].map(n => n.toString().padStart(2, '0'));
    }

    // every second, we will check all locked content
    // and update countdowns accordingly
    function tick() {
        setTimeout(function () {
            locks = locks.map(processLockTick);
            locks = locks.filter(lock => lock !== null);
            tick();
        }, 1000);
    }

    $(document).ready(function () {
        loadContentLocks();
        tick();
    });
})(jQuery);
