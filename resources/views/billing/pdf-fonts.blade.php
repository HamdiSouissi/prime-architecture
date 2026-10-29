@foreach([400,700] as $weight)
@font-face { font-family: Inter; src: url(data:font/woff2;base64,{{ $fonts['inter-latin-'.$weight] }}) format('woff2'); font-weight: {{ $weight }}; unicode-range: U+0000-00FF; }
@font-face { font-family: Inter; src: url(data:font/woff2;base64,{{ $fonts['inter-latin-ext-'.$weight] }}) format('woff2'); font-weight: {{ $weight }}; unicode-range: U+0100-024F, U+1E00-1EFF, U+2000-206F, U+20A0-20AB, U+20AD-20CF, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: Cairo; src: url(data:font/woff2;base64,{{ $fonts['cairo-latin-'.$weight] }}) format('woff2'); font-weight: {{ $weight }}; unicode-range: U+0000-024F, U+2000-206F; }
@font-face { font-family: Cairo; src: url(data:font/woff2;base64,{{ $fonts['cairo-arabic-'.$weight] }}) format('woff2'); font-weight: {{ $weight }}; unicode-range: U+0600-06FF, U+0750-077F, U+08A0-08FF, U+FB50-FDFF, U+FE70-FEFF; }
@endforeach
