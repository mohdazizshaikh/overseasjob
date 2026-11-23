"""M16 targeted keyboard, form-label, reflow, print and visual-baseline browser QA.

Runs against isolated PHP preview. NOT WCAG certification, real Hostinger proof
or comparison with the unavailable original live theme export.
"""
from pathlib import Path
from playwright.sync_api import sync_playwright
import os

BASE=os.environ.get('OJ_E2E_BASE','http://127.0.0.1:8809').rstrip('/')
OUT=Path('artifacts/isolated-visual-baseline')
OUT.mkdir(parents=True,exist_ok=True)

ROUTES=[
    '/', '/jobs','/tools','/tools/salary-savings','/tools/relocation-budget',
    '/tools/application-tracker','/tools/resume-kit','/tools/interview-practice',
    '/tools/country-checklist','/tools/workproof','/tools/career-roadmap',
    '/my-career','/daily','/guides','/safety','/privacy'
]
count=0
def check(label,condition,detail=''):
    global count
    if not condition:
        raise AssertionError('FAIL '+label+': '+detail)
    count+=1
    print('PASS M16 '+label)

FORM_ACCESSIBLE_NAMES=r"""() => {
    const visible = el => {
        const st=getComputedStyle(el);
        return el.getClientRects().length > 0 && st.display !== 'none' && st.visibility !== 'hidden';
    };
    const fields=[...document.querySelectorAll('input,textarea,select')].filter(visible);
    const unlabeled=[];
    for (const el of fields) {
        if (['hidden','submit','button','reset','image'].includes(el.type)) continue;
        const labelled=(el.labels && el.labels.length > 0)
          || (el.getAttribute('aria-label') || '').trim().length > 0
          || (el.getAttribute('aria-labelledby') || '').trim().length > 0;
        if (!labelled) unlabeled.push(el.tagName.toLowerCase()+'[name='+(el.name||'none')+']#'+(el.id||'none'));
    }
    const missingButton=[...document.querySelectorAll('button')].filter(visible).filter(el =>
      !(el.textContent || '').trim()
      && !(el.getAttribute('aria-label') || '').trim()
      && !(el.getAttribute('title') || '').trim()).map(el=>el.outerHTML.slice(0,120));
    return {unlabeled,missingButton,imagesMissingAlt:[...document.images].filter(el=>!el.hasAttribute('alt')).map(el=>el.src)};
}"""

with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,args=['--no-sandbox','--disable-dev-shm-usage'])
    desktop=browser.new_context(viewport={'width':1360,'height':900},color_scheme='light')
    page=desktop.new_page()
    errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    for route in ROUTES:
        page.goto(BASE+route,wait_until='networkidle')
        check('single main landmark '+route,page.locator('main#main').count()==1)
        check('one primary heading '+route,page.locator('h1').count()==1)
        check('English document language '+route,page.locator('html').get_attribute('lang')=='en')
        inspect=page.evaluate(FORM_ACCESSIBLE_NAMES)
        check('visible form fields have labels '+route,len(inspect['unlabeled'])==0,str(inspect['unlabeled']))
        check('buttons and images have names/alt '+route,
              not inspect['missingButton'] and not inspect['imagesMissingAlt'],str(inspect))
        if route in ('/','/jobs','/tools','/guides'):
            name=route.strip('/').replace('/','-') or 'home'
            page.screenshot(path=str(OUT/('desktop-'+name+'.png')),full_page=True,animations='disabled')
    page.goto(BASE+'/',wait_until='networkidle')
    page.keyboard.press('Tab')
    check('keyboard first Tab reaches skip-to-content',
          page.evaluate("document.activeElement?.classList.contains('skip-link')") is True)
    check('keyboard skip-link becomes visible',
          page.locator('.skip-link').evaluate("el => getComputedStyle(el).top")=='10px')
    focused=page.evaluate("getComputedStyle(document.activeElement).outlineWidth")
    check('keyboard focus has nonzero outline',float(focused.removesuffix('px'))>=2,focused)
    check('desktop core pages have no uncaught JavaScript exceptions',not errors,str(errors))

    narrow=browser.new_context(viewport={'width':320,'height':720},is_mobile=True,
                               has_touch=True,device_scale_factor=1)
    phone=narrow.new_page()
    phoneerrors=[]
    phone.on('pageerror',lambda e:phoneerrors.append(str(e)))
    for route in ROUTES:
        phone.goto(BASE+route,wait_until='networkidle')
        dimensions=phone.evaluate("() => ({inner: window.innerWidth, width: document.documentElement.scrollWidth})")
        check('320 CSS px reflow without horizontal scroll '+route,
              dimensions['width']<=dimensions['inner']+3,str(dimensions))
        if route in ('/','/jobs','/tools','/guides'):
            name=route.strip('/').replace('/','-') or 'home'
            phone.screenshot(path=str(OUT/('mobile-'+name+'.png')),full_page=True,animations='disabled')
    check('narrow mobile pages have no uncaught JS exceptions',not phoneerrors,str(phoneerrors))

    page.goto(BASE+'/tools/career-roadmap',wait_until='networkidle')
    page.locator('#roadmap-form [name="hours"]').fill('3')
    page.locator('#roadmap-form button[type="submit"]').click()
    check('print fixture generates roadmap results',page.locator('#roadmap-result').count()>=1)
    page.emulate_media(media='print')
    check('print stylesheet hides top navigation on roadmap',
          page.locator('.header-inner').evaluate("el => getComputedStyle(el).display")=='none')
    check('print stylesheet removes roadmap preview clipping',
          page.locator('.roadmap-preview').evaluate("el => getComputedStyle(el).maxHeight")=='none')
    page.emulate_media(media='screen')

    nojs=browser.new_context(java_script_enabled=False,viewport={'width':1360,'height':900})
    static=nojs.new_page()
    for route in ('/','/jobs','/safety'):
        response=static.goto(BASE+route,wait_until='domcontentloaded')
        check('core route renders with JavaScript unavailable '+route,
              response.status==200 and static.locator('h1').count()==1)
    nojs.close();narrow.close();desktop.close();browser.close()

print('\n'+str(count)+' isolated M16 keyboard/reflow/print/form-label checks passed.')
print('Isolated new-frontend screenshots at '+str(OUT)+'; NOT verified original-site visual parity or real-host accessibility.')
