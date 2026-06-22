let loading = false;
let noMorePosts = false;
let scrollTicking = false;

function buildApiUrl() {
    let url = apiUrl + '?offset=' + currentOffset + '&limit=' + postsPerLoad;
    for (let key in apiParams) {
        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(apiParams[key]);
    }
    return url;
}

function loadMorePosts() {
    if (loading || noMorePosts) return;
    loading = true;
    const spinner = document.getElementById('loading-spinner');
    if (spinner) spinner.style.display = 'block';
    fetch(buildApiUrl())
        .then(response => {
            if (response.status === 204) {
                noMorePosts = true;
                if (spinner) spinner.style.display = 'none';
                window.removeEventListener('scroll', scrollHandler);
                return null;
            }
            return response.text();
        })
        .then(html => {
            if (html === null) return;
            if (html.trim() !== '') {
                document.getElementById('posts-container').insertAdjacentHTML('beforeend', html);
                currentOffset += postsPerLoad;
            } else {
                noMorePosts = true;
                window.removeEventListener('scroll', scrollHandler);
            }
            if (spinner) spinner.style.display = 'none';
            loading = false;
        })
        .catch(() => {
            if (spinner) spinner.style.display = 'none';
            loading = false;
            const errMsg = document.getElementById('load-error');
            if (errMsg) errMsg.style.display = 'block';
        });
}

const scrollHandler = function() {
    if (loading || noMorePosts) return;
    if (!scrollTicking) {
        window.requestAnimationFrame(function() {
            if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 200) {
                loadMorePosts();
            }
            scrollTicking = false;
        });
        scrollTicking = true;
    }
};

window.addEventListener('scroll', scrollHandler);