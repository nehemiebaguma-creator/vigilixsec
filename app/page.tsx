import { ShieldCheck, Camera, Bell, Users, ArrowUpRight, Activity, LockKeyhole } from 'lucide-react'
import styles from './page.module.css'

const signals = [
  { label: 'Zones protégées', value: '24', detail: '+3 ce mois', icon: ShieldCheck },
  { label: 'Caméras actives', value: '18/20', detail: '98.4% uptime', icon: Camera },
  { label: 'Alertes traitées', value: '142', detail: '-12% cette semaine', icon: Bell },
  { label: 'Utilisateurs autorisés', value: '36', detail: '4 administrateurs', icon: Users },
]

export default function Home() {
  return <main className={styles.shell}>
    <aside className={styles.sidebar}>
      <div className={styles.brand}><span className={styles.brandMark}><ShieldCheck size={19}/></span><span>VIGILIX<span className={styles.accent}>SEC</span></span></div>
      <div className={styles.eyebrow}>CENTRE DE CONTRÔLE</div>
      <nav className={styles.nav} aria-label="Navigation principale">
        <a className={styles.active} href="#dashboard"><Activity size={17}/>Vue d&apos;ensemble</a>
        <a href="#cameras"><Camera size={17}/>Caméras <span className={styles.count}>20</span></a>
        <a href="#alerts"><Bell size={17}/>Alertes <span className={styles.alertCount}>3</span></a>
        <a href="#access"><LockKeyhole size={17}/>Contrôle d&apos;accès</a>
      </nav>
      <div className={styles.sidebarBottom}><div className={styles.statusDot}/>Système opérationnel<div className={styles.version}>v2.4.1</div></div>
    </aside>
    <section className={styles.content}>
      <header className={styles.header}><div><p className={styles.kicker}>LUNDI 8 SEPTEMBRE 2026 · 09:41</p><h1>Bonjour, administrateur.</h1><p className={styles.subtitle}>Voici l&apos;état de votre dispositif de sécurité.</p></div><div className={styles.user}><div className={styles.avatar}>AD</div><div><strong>Admin Vigilix</strong><span>Administrateur</span></div></div></header>
      <div className={styles.rule}/>
      <div className={styles.stats}>{signals.map(({label,value,detail,icon: Icon}) => <article className={styles.stat} key={label}><div className={styles.statTop}><span>{label}</span><Icon size={18}/></div><strong>{value}</strong><small>{detail}</small></article>)}</div>
      <div className={styles.grid}>
        <section className={styles.panel}><div className={styles.panelHead}><div><p className={styles.kicker}>SURVEILLANCE</p><h2>Activité en temps réel</h2></div><button className={styles.outline}>Voir le journal <ArrowUpRight size={15}/></button></div><div className={styles.activity}><div className={styles.chart}><div className={styles.chartLabel}>Événements détectés <strong>28</strong></div><div className={styles.bars}>{[35,48,42,68,54,72,61,86,74,92,66,78].map((height,i)=><span key={i} style={{height:`${height}%`}}/>)}</div><div className={styles.axis}><span>00h</span><span>06h</span><span>12h</span><span>18h</span><span>24h</span></div></div><div className={styles.events}><div className={styles.event}><span className={styles.live}/><div><strong>Entrée principale</strong><small>Accès autorisé · Il y a 2 min</small></div><b>OK</b></div><div className={styles.event}><span className={styles.warn}/><div><strong>Zone parking B</strong><small>Mouvement détecté · Il y a 11 min</small></div><b className={styles.warnText}>À vérifier</b></div><div className={styles.event}><span className={styles.live}/><div><strong>Serveur local</strong><small>Synchronisation terminée · Il y a 24 min</small></div><b>OK</b></div></div></div></section>
        <section className={styles.panel}><div className={styles.panelHead}><div><p className={styles.kicker}>ÉTAT DU RÉSEAU</p><h2>Vos équipements</h2></div><button className={styles.iconButton} aria-label="Actualiser">↻</button></div><div className={styles.devices}><div><span className={styles.deviceIcon}><Camera size={17}/></span><span><strong>Caméras</strong><small>18 actives sur 20</small></span><em>90%</em></div><div><span className={styles.deviceIcon}><ShieldCheck size={17}/></span><span><strong>Capteurs</strong><small>64 actifs sur 64</small></span><em>100%</em></div><div><span className={styles.deviceIcon}><LockKeyhole size={17}/></span><span><strong>Points d&apos;accès</strong><small>12 actifs sur 12</small></span><em>100%</em></div></div><button className={styles.primary}>Ouvrir le centre de supervision <ArrowUpRight size={15}/></button></section>
      </div>
      <footer className={styles.footer}>VIGILIXSEC · Plateforme de sécurité unifiée <span>Assistance disponible 24/7</span></footer>
    </section>
  </main>
}
