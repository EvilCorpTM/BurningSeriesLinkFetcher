# navigate into dl/SeriesName/
# run this
for row in $(cat index.json | jq -r '.[] | @base64'); do
        _jq() {
                echo ${row} | base64 --decode | jq -r ${1}
        }
        title=$(_jq '.url' | cut -d'/' -f 7)
        #echo $title
        youtube-dl $(_jq '.link') -o $(_jq '.season')"/$title.%(ext)s"
        #echo $(_jq '.season')
        #echo $(_jq '.url')
        #echo $(_jq '.link')
done
