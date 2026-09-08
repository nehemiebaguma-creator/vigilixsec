'use client'

import { useEffect, useState } from 'react'
import { ShieldCheck, Camera, Bell, Users, ArrowUpRight, Activity, LockKeyhole, LogOut } from 'lucide-react'
import { createClient } from '@/lib/supabase/client'
import styles from './page.module.css'

export default function Home() {
  const supabase = createClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [userEmail, setUserEmail] = useState<string | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [deviceCount, setDeviceCount] = useState('18/20')
  const [alertCount, setAlertCount] = useState('3')
  const signals = [
    { label: 'Zones protégées', value: '24', detail: '+3 ce mois', icon: ShieldCheck },
    { label: 'Caméras actives', value: deviceCount, detail: 'Données Supabase', icon: Camera },
    { label: 'Alertes ouvertes', value: alertCount, detail: 'Données Supabase', icon: Bell },
    { label: 'Utilisateurs autorisés', value: '36', detail: '4 administrateurs', icon: Users },
  ]

  useEffect(() => {
    supabase.auth.getUser().then(async ({ data }) => {
      setUserEmail(data.user?.email ?? null)
      if (!data.user) return
      const [{ count: devices }, { count: alerts }] = await Promise.all([
        supabase.from('devices').select('id', { count: 'exact', head: true }).eq('user_id', data.user.id).eq('status', 'active'),
        supabase.from('security_alerts').select('id', { count: 'exact', head: true }).eq('user_id', data.user.id).eq('status', 'open'),
      ])
      if (devices !== null) setDeviceCount(`${devices}/20`)
      if (alerts !== null) setAlertCount(String(alerts))
    })
    const { data: listener } = supabase.auth.onAuthStateChange((_event, session) => setUserEmail(session?.user?.email ?? null))
    return () => listener.subscription.unsubscribe()
  }, [supabase])

  async function signIn(event: React.FormEvent) {
    event.preventDefault(); setLoading(true); setError('')
    const { error: authError } = await supabase.auth.signInWithPassword({ email, password })
    if (authError) setError('Email ou mot de passe invalide.')
    setLoading(false)
  }

  if (!userEmail) return <main className={styles.loginShell}><div className={styles.loginCard}><div className={styles.brand}><span className={styles.brandMark}><ShieldCheck size={19}/></span><span>VIGILIX<span className={styles.accent}>SEC</span></span></div><p className={styles.kicker}>ACCÈS SÉCURISÉ</p><h1>Centre de contrôle</h1><p className={styles.subtitle}>Connectez-vous pour accéder à votre dispositif.</p><form onSubmit={signIn} className={styles.loginForm}><label>Email<input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} /></label><label>Mot de passe<input type="password" required value={password} onChange={(event) => setPassword(event.target.value)} /></label>{error && <p className={styles.error}>{error}</p>}<button className={styles.primary} disabled={loading}>{loading ? 'Connexion...' : 'Se connecter'}<ArrowUpRight size={15}/></button></form></div></main>

  return <main className={styles.shell}>
    <aside className={styles.sidebar}><div className={styles.brand}><span className={styles.brandMark}><ShieldCheck size={19}/></span><span>VIGILIX<span className={styles.accent}>SEC</span></span></div><div className={styles.eyebrow}>CENTRE DE CONTRÔLE</div><nav className={styles.nav} aria-label="Navigation principale"><a className={styles.active} href="#dashboard"><Activity size={17}/>Vue d&apos;ensemble</a><a href="#cameras"><Camera size={17}/>Caméras <span className={styles.count}>20</span></a><a href="#alerts"><Bell size={17}/>Alertes <span className={styles.alertCount}>3</span></a><a href="#access"><LockKeyhole size={17}/>Contrôle d&apos;accès</a></nav><div className={styles.sidebarBottom}><div className={styles.statusDot}/>Système opérationnel<div className={styles.version}>v2.4.1</div></div></aside>
    <section className={styles.content}><header className={styles.header}><div><p className={styles.kicker}>LUNDI 8 SEPTEMBRE 2026 · 09:41</p><h1>Bonjour, administrateur.</h1><p className={styles.subtitle}>Voici l&apos;état de votre dispositif de sécurité.</p></div><div className={styles.user}><div className={styles.avatar}>{userEmail.slice(0, 2).toUpperCase()}</div><div><strong>{userEmail}</strong><span>Compte sécurisé</span></div><button className={styles.iconButton} aria-label="Se déconnecter" onClick={() => supabase.auth.signOut()}><LogOut size={16}/></button></div></header><div className={styles.rule}/><div className={styles.stats}>{signals.map(({label,value,detail,icon: Icon}) => <article className={styles.stat} key={label}><div className={styles.statTop}><span>{label}</span><Icon size={18}/></div><strong>{value}</strong><small>{detail}</small></article>)}</div><div className={styles.grid}><section className={styles.panel}><div className={styles.panelHead}><div><p className={styles.kicker}>SURVEILLANCE</p><h2>Activité en temps réel</h2></div><button className={styles.outline}>Voir le journal <ArrowUpRight size={15}/></button></div><div className={styles.activity}><div className={styles.chart}><div className={styles.chartLabel}>Événements détectés <strong>28</strong></div><div className={styles.bars}>{[35,48,42,68,54,72,61,86,74,92,66,78].map((height,i)=><span key={i} style={{height:`${height}%`}}/>)}</div><div className={styles.axis}><span>00h</span><span>06h</span><span>12h</span><span>18h</span><span>24h</span></div></div><div className={styles.events}><div className={styles.event}><span className={styles.live}/><div><strong>Entrée principale</strong><small>Accès autorisé · Il y a 2 min</small></div><b>OK</b></div><div className={styles.event}><span className={styles.warn}/><div><strong>Zone parking B</strong><small>Mouvement détecté · Il y a 11 min</small></div><b className={styles.warnText}>À vérifier</b></div><div className={styles.event}><span className={styles.live}/><div><strong>Serveur local</strong><small>Synchronisation terminée · Il y a 24 min</small></div><b>OK</b></div></div></div></section><section className={styles.panel}><div className={styles.panelHead}><div><p className={styles.kicker}>ÉTAT DU RÉSEAU</p><h2>Vos équipements</h2></div><button className={styles.iconButton} aria-label="Actualiser">↻</button></div><div className={styles.devices}><div><span className={styles.deviceIcon}><Camera size={17}/></span><span><strong>Caméras</strong><small>18 actives sur 20</small></span><em>90%</em></div><div><span className={styles.deviceIcon}><ShieldCheck size={17}/></span><span><strong>Capteurs</strong><small>64 actifs sur 64</small></span><em>100%</em></div><div><span className={styles.deviceIcon}><LockKeyhole size={17}/></span><span><strong>Points d&apos;accès</strong><small>12 actifs sur 12</small></span><em>100%</em></div></div><button className={styles.primary}>Ouvrir le centre de supervision <ArrowUpRight size={15}/></button></section></div><footer className={styles.footer}>VIGILIXSEC · Plateforme de sécurité unifiée <span>Assistance disponible 24/7</span></footer></section>
  </main>
}
