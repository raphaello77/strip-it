# strip-it

Example of use
``` <product-slider :slides="{{ slides | strip_it::size,slug | to_json | entities }}" />```

Or like this, where 'title, name' always get removed while 'size,slug' never get removed.
```<product-slider :slides="{{ slides | strip_it:title,name:size,slug | to_json | entities }}" />```

Output should hopefully result in something looking for example like this
```
{
    "items":
    [
        {
            "content": "<p>A book about Anna.</p>\n",
            "image":
            {
                "items":
                [
                    {
                        "title": "bck.jpg",
                        "url": "/assets/bck.jpg"
                    }
                ]
            },
            "slug": "anna-karenina",
            "title": "Anna Karenina"
        },
        {
            "content": "<p>A book about Harry Potter.</p>\n",
            "image":
            {
                "items":
                [
                    {
                        "title": "harry.jpg",
                        "url": "/assets/harry.jpg"
                    }
                ]
            },
            "slug": "harry-potter",
            "title": "Harry Potter"
        }
    ]
}
```



