#!/usr/bin/env python3
"""
Comprueba un JSON de importación del ranking interno del Club Triatlón Sulayr y resume lo que importará.

Aplica las mismas reglas que la web (ImportParser e ImportMerger del plugin club-triatlon-sulayr):
si este script da el JSON por bueno, la web también. Solo usa la biblioteca estándar, para que
funcione igual en Claude Code que en la app de escritorio o claude.ai.

Uso: python3 validar.py <resultados.json> [AAAA-MM-DD de hoy]
Sale con código 1 si el JSON tiene errores.
"""
import datetime
import json
import re
import sys
import unicodedata

SCHEMA = 'sulayr-ranking-import/1'
MODALITIES = ['triatlon', 'triatlon_cros', 'duatlon', 'duatlon_cros', 'acuatlon', 'acuabike']
DISTANCES = ['supersprint', 'sprint', 'estandar', 'media', 'larga']
STATUSES = ['finalizado', 'dnf', 'dsq', 'dns', 'nc']
TEAM_CATEGORIES = ['m', 'f', 'mixto']
CLUB_KEYWORD = 'sulayr'


def parse_time(value):
    """Igual que RaceTime::parse(): h:mm:ss (minutos ≤ 59) o mm:ss; menos de 24 h."""
    value = str(value).strip()
    m = re.fullmatch(r'(\d{1,2}):(\d{2}):(\d{2})', value)
    if m:
        h, mi, s = (int(x) for x in m.groups())
        if mi > 59:
            return None
    else:
        m = re.fullmatch(r'(\d{1,3}):(\d{2})', value)
        if not m:
            return None
        h, mi, s = 0, int(m.group(1)), int(m.group(2))
    if s > 59:
        return None
    total = h * 3600 + mi * 60 + s
    return total if 0 < total < 86400 else None


def fmt(seconds):
    return '—' if seconds is None else '%d:%02d:%02d' % (seconds // 3600, seconds % 3600 // 60, seconds % 60)


def text(value):
    if isinstance(value, (dict, list)) or value is None:
        return ''
    return re.sub(r'\s+', ' ', str(value)).strip()


def is_int(value):
    return isinstance(value, int) and not isinstance(value, bool) or (isinstance(value, str) and re.fullmatch(r'\d+', value.strip()) is not None)


def parse(raw, today):
    errors = []
    raw = raw.lstrip('﻿').strip()
    m = re.fullmatch(r'```[a-z]*\s*(.*?)\s*```', raw, re.S)
    if m:
        raw = m.group(1)
    try:
        data = json.loads(raw)
    except ValueError as e:
        return None, ['No es un JSON válido: %s.' % e]
    if not isinstance(data, dict) or data.get('schema') != SCHEMA:
        return None, ['Falta "schema": "%s" o tiene otro valor.' % SCHEMA]

    c = data.get('competition') or {}
    comp = {
        'name': text(c.get('name')),
        'race_date': text(c.get('race_date')),
        'location': text(c.get('location')),
        'modality': text(c.get('modality')).lower(),
        'distance': text(c.get('distance')).lower(),
        'format': text(c.get('format')).lower(),
    }
    if not comp['name']:
        errors.append('Competición: falta "name".')
    try:
        if not re.fullmatch(r'\d{4}-\d{2}-\d{2}', comp['race_date']):
            raise ValueError
        datetime.date.fromisoformat(comp['race_date'])
        if comp['race_date'] > today:
            errors.append('Competición: la fecha no puede ser posterior a hoy.')
    except ValueError:
        errors.append('Competición: "race_date" tiene que ser una fecha AAAA-MM-DD.')
    if comp['modality'] not in MODALITIES:
        errors.append('Competición: "modality" tiene que ser uno de %s.' % ', '.join(MODALITIES))
    if comp['distance'] not in DISTANCES:
        errors.append('Competición: "distance" tiene que ser uno de %s.' % ', '.join(DISTANCES))
    if comp['format'] == 'equipos':
        errors.append('Competición: la importación todavía no admite la contrarreloj por equipos. Métela a mano.')
    elif comp['format'] not in ('individual', 'relevos'):
        errors.append('Competición: "format" tiene que ser "individual" o "relevos".')

    classifications = data.get('classifications')
    if not isinstance(classifications, list) or not classifications:
        errors.append('Falta "classifications" o está vacía.')
        return None, errors

    out = {'competition': comp, 'classifications': [], 'athletes': []}
    for ci, cl in enumerate(classifications):
        cl = cl if isinstance(cl, dict) else {}
        title = text(cl.get('title'))
        where = 'Clasificación %d («%s»)' % (ci + 1, title or 'sin título')
        team = text(cl.get('team_category')).lower()
        if comp['format'] == 'relevos' and team not in TEAM_CATEGORIES:
            errors.append(where + ': en relevos, "team_category" tiene que ser "m", "f" o "mixto".')
        elif comp['format'] == 'individual':
            team = ''
        finishers = cl.get('finishers')
        if finishers in ('', None):
            finishers = None
        elif not is_int(finishers) or int(finishers) < 1:
            errors.append(where + ': "finishers" tiene que ser un número mayor que 0.')
            finishers = None
        else:
            finishers = int(finishers)
        out['classifications'].append({'title': title, 'team_category': team, 'finishers': finishers})

        athletes = cl.get('athletes')
        if not isinstance(athletes, list) or not athletes:
            errors.append(where + ': falta "athletes" o está vacía.')
            continue
        for ai, a in enumerate(athletes):
            a = a if isinstance(a, dict) else {}
            row = {
                'classification': ci, 'team_category': team,
                'name': text(a.get('name')), 'club': text(a.get('club')),
                'cat': text(a.get('cat')).upper(),
                'status': text(a.get('status')).lower() or 'finalizado',
                'sex': text(a.get('sex')).lower(),
                'pos': None, 'pos_cat': None, 'time_s': None,
            }
            w = '%s, deportista %d' % (where, ai + 1)
            if not row['name']:
                errors.append(w + ': falta "name".')
            else:
                w += ' («%s»)' % row['name']
            if row['cat'] and not re.fullmatch(r'[A-Z0-9]{2}[MF]', row['cat']):
                errors.append(w + ': "cat" son dos caracteres y la M o la F (p. ej. V1M).')
            if row['status'] not in STATUSES:
                errors.append(w + ': "status" tiene que ser uno de %s.' % ', '.join(STATUSES))
            cat_sex = row['cat'][-1:].lower() if row['cat'] else ''
            if row['sex'] and row['sex'] not in ('m', 'f'):
                errors.append(w + ': "sex" tiene que ser "m" o "f".')
                row['sex'] = ''
            elif row['sex'] and cat_sex in ('m', 'f') and cat_sex != row['sex']:
                errors.append(w + ': "sex" no coincide con la letra final de "cat".')
            elif not row['sex'] and cat_sex in ('m', 'f'):
                row['sex'] = cat_sex
            for field in ('pos', 'pos_cat'):
                value = a.get(field)
                if value in (None, ''):
                    continue
                if not is_int(value) or int(value) < 1:
                    errors.append('%s: "%s" tiene que ser un número entero mayor que 0.' % (w, field))
                else:
                    row[field] = int(value)
            time = text(a.get('time'))
            if row['status'] == 'finalizado':
                if time:
                    row['time_s'] = parse_time(time)
                    if row['time_s'] is None:
                        errors.append(w + ': "time" tiene que ser h:mm:ss.')
                elif row['pos'] is None:
                    errors.append(w + ': ha terminado y no tiene ni "time" ni "pos".')
            else:
                row['pos'] = row['pos_cat'] = None
            if not row['sex']:
                errors.append('«%s»: no se sabe su sexo. Pon "sex": "m" o "f", o la categoría con la M o la F al final.' % row['name'])
            out['athletes'].append(row)
    return (None if errors else out), errors


def merge(parsed):
    """Igual que ImportMerger::merge(): junta las clasificaciones por categoría de equipo y sexo."""
    athletes = parsed['athletes']
    warnings = []
    sexes = {}
    for a in athletes:
        sexes.setdefault(a['classification'], set()).add(a['sex'])
    groups = {}
    for i, a in enumerate(athletes):
        g = groups.setdefault((a['team_category'], a['sex']), {'sources': [], 'members': []})
        if a['classification'] not in g['sources']:
            g['sources'].append(a['classification'])
        g['members'].append(i)

    stats = {}
    for key, g in sorted(groups.items()):
        finished = [i for i in g['members'] if athletes[i]['status'] == 'finalizado']
        timed = [i for i in finished if athletes[i]['time_s'] is not None]
        for i in finished:
            a = athletes[i]
            if a['time_s'] is None:
                if len(g['sources']) > 1:
                    warnings.append('«%s» terminó sin tiempo y su clasificación se ha unido a otra: su «Pos» es la del PDF, revísala.' % a['name'])
                continue
            a['pos'] = 1 + sum(1 for j in timed if athletes[j]['time_s'] < a['time_s'])
            a['pos_cat'] = 1 + sum(1 for j in timed if athletes[j]['time_s'] < a['time_s'] and athletes[j]['cat'] == a['cat']) if a['cat'] else None
        declared = 0
        for c in g['sources']:
            value = parsed['classifications'][c]['finishers']
            if value is None or len(sexes.get(c, ())) > 1:
                declared = None
                break
            declared += value
        if declared is not None and declared != len(finished):
            warnings.append('%s: el PDF dice %d finalizados y el JSON trae %d. Puede faltar algún deportista; la web usa la cifra del PDF.' % (label(*key), declared, len(finished)))
        times = [athletes[i]['time_s'] for i in timed]
        stats[key] = {
            'sources': g['sources'],
            'winner': min(times) if times else None,
            'participants': declared if declared is not None else len(finished),
        }
    return stats, warnings


def label(team, sex):
    sex_label = 'mujeres' if sex == 'f' else 'hombres'
    teams = {'m': 'Relevos masculinos', 'f': 'Relevos femeninos', 'mixto': 'Relevos mixtos'}
    return ('General de ' + sex_label) if not team else '%s, %s' % (teams.get(team, team), sex_label)


def plain(value):
    return ''.join(ch for ch in unicodedata.normalize('NFD', value.lower()) if unicodedata.category(ch) != 'Mn')


def main():
    if len(sys.argv) < 2:
        print('Uso: python3 validar.py <resultados.json> [AAAA-MM-DD]', file=sys.stderr)
        return 2
    today = sys.argv[2] if len(sys.argv) > 2 else datetime.date.today().isoformat()
    with open(sys.argv[1], encoding='utf-8') as f:
        parsed, errors = parse(f.read(), today)
    if errors:
        print('ERRORES (la web no lo aceptará):')
        for e in errors:
            print('  - ' + e)
        return 1

    c = parsed['competition']
    print('OK: %s · %s · %s · %s · %s · %s\n' % (c['name'], c['race_date'], c['location'], c['modality'], c['distance'], c['format']))
    print('Clasificaciones del JSON:')
    for i, cl in enumerate(parsed['classifications']):
        counts = {}
        for a in parsed['athletes']:
            if a['classification'] == i:
                counts[a['status']] = counts.get(a['status'], 0) + 1
        kind = ('relevos ' + cl['team_category']) if cl['team_category'] else 'individual'
        declared = (' · FINALIZADOS en el PDF: %d' % cl['finishers']) if cl['finishers'] is not None else ''
        print('  - %s (%s): %s%s' % (cl['title'], kind, ', '.join('%s: %d' % kv for kv in counts.items()), declared))

    stats, warnings = merge(parsed)
    print('\nConjuntos del ranking (clasificaciones unificadas):')
    for key, s in stats.items():
        titles = ' + '.join(parsed['classifications'][i]['title'] for i in s['sources'])
        print('  - %s: %s · ganador %s · finalizados %d' % (label(*key), titles, fmt(s['winner']), s['participants']))
    if warnings:
        print('\nAVISOS:')
        for w in warnings:
            print('  - ' + w)

    club = [a for a in parsed['athletes'] if CLUB_KEYWORD in plain(a['club'])]
    club.sort(key=lambda a: (a['team_category'], a['sex'], a['pos'] if a['pos'] is not None else 10 ** 9))
    print('\nDeportistas de equipos del club (Pos y Pos Cat ya unificadas):')
    for a in club:
        time = fmt(a['time_s']) if a['time_s'] is not None else a['status'].upper()
        print('  - %-5s%-38s%-5s%-4s%-9s%s' % (a['pos'] or '—', a['name'], a['cat'], a['pos_cat'] or '—', time, a['club']))
    print('\nTotal: %d deportistas, %d de equipos del club.' % (len(parsed['athletes']), len(club)))
    return 0


if __name__ == '__main__':
    sys.exit(main())
