<div {{ $attributes->class(['card p-5 bg-base-100 shadow grow-2']) }}>
    <h3 class="card-title font-bold text-lg px-2">Make Announcement</h3>
    <div class="card-body">
        <input type="text" name="ann_title" id="ann_title" class="input w-full" placeholder="Announcement Title">
        <textarea name="ann_text" id="ann_text" cols="30" placeholder="Enter announcement text here."
            class="textarea w-full"></textarea>
        <button class="btn btn-primary hover">Post</button>
    </div>
</div>
