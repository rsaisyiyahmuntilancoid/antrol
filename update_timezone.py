import re

with open('/home/me/Developer/RSAM/antrol/app/Services/MobileJknService.php', 'r') as f:
    content = f.read()

# Replace Carbon::parse(expr) with Carbon::parse(expr, 'Asia/Jakarta')
# We need to be careful with nested parentheses. 
# A regex that matches up to the matching closing parenthesis:
def replace_carbon_parse(match):
    inner = match.group(1)
    if "'Asia/Jakarta'" in inner:
        return match.group(0)
    return f"Carbon::parse({inner}, 'Asia/Jakarta')"

# We'll use a simpler approach since we know the lines
lines = content.split('\n')
for i, line in enumerate(lines):
    if 'Carbon::parse(' in line and "'Asia/Jakarta'" not in line:
        # replace the last parenthesis of the parse call
        # this might be tricky, let's just do a string replacement for the specific lines
        pass

# Actually, let's just use Python's re with careful matching
# For Carbon::parse
# Carbon::parse(something) -> Carbon::parse(something, 'Asia/Jakarta')
# Let's replace line by line based on grep output:

content = re.sub(r"Carbon::parse\((str_replace\(' 00:00:00', '', \$regPeriksa->tgl_registrasi\) \. ' ' \. \$regPeriksa->jam_reg->toTimeString\(\))\)", r"Carbon::parse(\1, 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$datePart \. ' ' \. \$pemeriksaan->jam_rawat->toTimeString\(\)\)", r"Carbon::parse($datePart . ' ' . $pemeriksaan->jam_rawat->toTimeString(), 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$datePart \. ' ' \. \$resep->jam->toTimeString\(\)\)", r"Carbon::parse($datePart . ' ' . $resep->jam->toTimeString(), 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$datePart \. ' ' \. \$resep->jam_penyerahan->toTimeString\(\)\)", r"Carbon::parse($datePart . ' ' . $resep->jam_penyerahan->toTimeString(), 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$previousTask->waktu\)", r"Carbon::parse($previousTask->waktu, 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$regPeriksa->tgl_registrasi\)", r"Carbon::parse($regPeriksa->tgl_registrasi, 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::createFromDate\(\$year, \$month, \$day\)", r"Carbon::createFromDate($year, $month, $day, 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::now\(\)", r"Carbon::now('Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(\$expectedDateStr\)", r"Carbon::parse($expectedDateStr, 'Asia/Jakarta')", content)
content = re.sub(r"Carbon::parse\(explode\(' ', \$reg->tgl_registrasi\)\[0\] \. ' ' \. \(\$jadwal->jam_mulai \?\? '00:00:00'\)\)", r"Carbon::parse(explode(' ', $reg->tgl_registrasi)[0] . ' ' . ($jadwal->jam_mulai ?? '00:00:00'), 'Asia/Jakarta')", content)


with open('/home/me/Developer/RSAM/antrol/app/Services/MobileJknService.php', 'w') as f:
    f.write(content)

