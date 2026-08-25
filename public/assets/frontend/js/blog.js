document.addEventListener('DOMContentLoaded', () => {
    const loadMore = document.getElementById('loadMoreBlogPosts');
    const postsContainer = document.getElementById('blogPosts');
    if(!loadMore || !postsContainer) {
        return;
    }

    loadMore.addEventListener('click', async () => {
        if(loadMore.disabled) {
            return;
        }
        loadMore.disabled = true;
        try {
            const res = await fetch(`${loadMore.dataset.url}?offset=${loadMore.dataset.offset}`);
            const resData = await res.json();
            if(!resData.success) {
                return;
            }
            postsContainer.insertAdjacentHTML('beforeend', resData.data.html);
            loadMore.dataset.offset = resData.data.nextOffset;
            if(!resData.data.hasMore) {
                loadMore.remove();
            }
        } catch (e) {
            // Leave the button in place so the visitor can try again.
        } finally {
            loadMore.disabled = false;
        }
    });
});
