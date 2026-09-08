'use client'

import { FormEvent, useState } from 'react'

const services = [
  { code: '01', title: 'Surveillance intelligente', text: 'Une veille continue qui transforme les signaux faibles en décisions rapides.' },
  { code: '02', title: 'Intervention coordonnée', text: 'Une chaîne opérationnelle locale, claire et disponible quand chaque seconde compte.' },
  { code: '03', title: 'Protection sur mesure', text: 'Des dispositifs pensés pour vos sites, vos équipes et vos priorités.' },
]

const portalModules = [
  { label: 'Caméras en direct', value: '12', status: 'Flux actifs' },
  { label: 'Alertes ouvertes', value: '03', status: 'À traiter' },
  { label: 'Équipes terrain', value: '08', status: 'En service' },
]

export default function Home() {
  const [sent, setSent] = useState(false)

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSent(true)
  }

  return (
    <main className="min-h-screen overflow-hidden">
      <div className="grid-lines fixed inset-0 -z-10 opacity-40" />
      <div className="mx-auto max-w-7xl px-6 lg:px-10">
        <header className="flex items-center justify-between border-b border-border py-6">
          <a href="#top" className="flex items-center gap-3" aria-label="vigilance security, accueil">
            <span className="flex size-10 items-center justify-center border border-brand/40 bg-card font-mono text-sm text-brand">VS</span>
            <span className="font-mono text-sm font-semibold uppercase tracking-[0.24em]">vigilance security</span>
          </a>
          <nav className="hidden items-center gap-8 text-sm text-muted md:flex">
            <a href="#mission" className="transition-colors hover:text-foreground">Notre mission</a>
            <a href="#services" className="transition-colors hover:text-foreground">Expertise</a>
            <a href="#portal" className="text-brand transition-colors hover:text-foreground">Centre de contrôle</a>
            <a href="#contact" className="border border-brand/40 px-4 py-2 text-brand transition-colors hover:bg-brand hover:text-background">Parler à un expert</a>
          </nav>
        </header>

        <section id="top" className="grid min-h-[620px] items-center gap-16 py-20 lg:grid-cols-[1.1fr_.9fr] lg:py-28">
          <div className="reveal">
            <p className="mb-7 flex items-center gap-3 font-mono text-xs uppercase tracking-[0.28em] text-brand"><span className="h-px w-10 bg-brand" /> Centre de protection privé · Kinshasa</p>
            <h1 className="max-w-4xl text-balance text-5xl font-semibold leading-[1.04] tracking-[-0.06em] sm:text-7xl lg:text-8xl">Quand tout le monde dort, <span className="text-brand">nous veillons.</span></h1>
            <p className="mt-8 max-w-xl text-pretty text-lg leading-8 text-muted">vigilance security relie surveillance, alerte, coordination et intervention dans une seule chaîne opérationnelle.</p>
            <div className="mt-10 flex flex-wrap gap-4"><a href="#contact" className="bg-brand px-6 py-3.5 text-sm font-semibold text-background transition-transform hover:-translate-y-0.5">Sécuriser mon activité</a><a href="#portal" className="border border-border px-6 py-3.5 text-sm font-semibold text-foreground transition-colors hover:border-brand/60">Accéder au portail</a></div>
          </div>
          <div className="reveal-delay relative overflow-hidden border border-border bg-card p-7 sm:p-10"><div className="absolute inset-0 bg-[url('/ops-road-control-check.png')] bg-cover bg-center opacity-10" /><div className="relative"><div className="mb-14 flex items-center justify-between font-mono text-[11px] uppercase tracking-[0.2em] text-muted"><span>Vigilance / command</span><span className="flex items-center gap-2 text-signal"><i className="size-2 rounded-full bg-signal" /> Opérationnel</span></div><div className="relative mx-auto flex aspect-square max-w-[290px] items-center justify-center rounded-full border border-brand/30"><div className="absolute inset-8 rounded-full border border-brand/20" /><div className="absolute inset-20 rounded-full border border-brand/15" /><div className="size-24 rounded-full border border-brand/50 bg-brand/10 shadow-[0_0_80px_rgba(114,216,255,.2)]" /><span className="absolute top-5 font-mono text-[10px] uppercase tracking-[.25em] text-brand">Protection continue</span><span className="absolute bottom-5 font-mono text-[10px] uppercase tracking-[.25em] text-muted">-4.3250 / 15.3222</span></div><div className="mt-10 grid grid-cols-3 gap-3 border-t border-border pt-5 text-center font-mono text-[10px] uppercase tracking-wider text-muted"><span><b className="block text-xl text-foreground">24/7</b> veille</span><span><b className="block text-xl text-foreground">01</b> chaîne</span><span><b className="block text-xl text-foreground">RDC</b> terrain</span></div></div></div>
        </section>

        <section id="mission" className="border-t border-border py-24 lg:py-32"><div className="grid gap-10 lg:grid-cols-[.7fr_1.3fr]"><p className="font-mono text-xs uppercase tracking-[.25em] text-brand">/ Notre mission</p><div><h2 className="max-w-3xl text-balance text-4xl font-semibold leading-tight tracking-[-.04em] sm:text-6xl">Une présence discrète. Une réponse décisive.</h2><p className="mt-7 max-w-2xl text-lg leading-8 text-muted">Nous protégeons les personnes, les lieux et les opérations sensibles avec une approche pragmatique : comprendre votre environnement, anticiper les risques et agir avec précision.</p></div></div></section>

        <section id="services" className="border-t border-border py-24 lg:py-32"><div className="mb-14 flex flex-col justify-between gap-5 sm:flex-row sm:items-end"><div><p className="font-mono text-xs uppercase tracking-[.25em] text-brand">/ Expertise</p><h2 className="mt-4 text-4xl font-semibold tracking-[-.04em] sm:text-5xl">La sécurité, sans angle mort.</h2></div><p className="max-w-xs text-sm leading-6 text-muted">Un dispositif lisible, piloté et adapté aux réalités du terrain.</p></div><div className="grid gap-px border border-border bg-border md:grid-cols-3">{services.map((service) => <article key={service.code} className="bg-background p-8 transition-colors hover:bg-card"><span className="font-mono text-xs text-signal">{service.code}</span><h3 className="mt-16 text-2xl font-semibold tracking-tight">{service.title}</h3><p className="mt-4 leading-7 text-muted">{service.text}</p><span className="mt-10 block h-px w-12 bg-brand/60" /></article>)}</div></section>

        <section id="portal" className="border-t border-border py-24 lg:py-32"><div className="mb-12 flex flex-col justify-between gap-6 md:flex-row md:items-end"><div><p className="font-mono text-xs uppercase tracking-[.25em] text-brand">/ Portail client</p><h2 className="mt-4 max-w-3xl text-4xl font-semibold tracking-[-.04em] sm:text-6xl">Le centre de contrôle, à portée de main.</h2><p className="mt-5 max-w-xl leading-7 text-muted">Connectez-vous à votre espace sécurisé pour superviser vos caméras, suivre les alertes et coordonner vos équipes.</p></div><a href="/login-page.html" className="shrink-0 border border-brand/50 px-6 py-3.5 text-center text-sm font-semibold text-brand transition-colors hover:bg-brand hover:text-background">Ouvrir le portail client</a></div><div className="grid gap-5 lg:grid-cols-[1.15fr_.85fr]"><div className="overflow-hidden border border-border bg-card"><div className="relative h-64 sm:h-80"><img src="/ops-road-control-check.png" alt="Opérateur du centre de contrôle vigilance security" className="size-full object-cover opacity-80" /><div className="absolute inset-0 bg-gradient-to-t from-background via-transparent to-transparent" /><span className="absolute bottom-5 left-5 font-mono text-xs uppercase tracking-[.2em] text-brand">Supervision terrain / live</span></div><div className="grid gap-px bg-border sm:grid-cols-3">{portalModules.map((module) => <div key={module.label} className="bg-card p-5"><span className="font-mono text-3xl text-foreground">{module.value}</span><p className="mt-2 text-sm text-muted">{module.label}</p><p className="mt-3 font-mono text-[10px] uppercase tracking-wider text-signal">{module.status}</p></div>)}</div></div><div className="overflow-hidden border border-border bg-card"><img src="/bus.jpg" alt="Véhicule sécurisé suivi par vigilance security" className="h-52 w-full object-cover opacity-75" /><div className="p-7"><p className="font-mono text-xs uppercase tracking-[.2em] text-brand">Accès par rôle</p><h3 className="mt-4 text-2xl font-semibold">Une vue claire pour chaque équipe.</h3><ul className="mt-6 flex flex-col gap-4 text-sm leading-6 text-muted"><li><span className="mr-2 text-signal">→</span> Direction : rapports et indicateurs</li><li><span className="mr-2 text-signal">→</span> Opérations : alertes et interventions</li><li><span className="mr-2 text-signal">→</span> Client : caméras et demandes</li></ul></div></div></div></section>

        <section id="contact" className="mb-16 grid gap-10 border border-brand/25 bg-card p-8 sm:p-12 lg:grid-cols-[.9fr_1.1fr] lg:items-end"><div><p className="font-mono text-xs uppercase tracking-[.25em] text-brand">/ Demander une étude</p><h2 className="mt-5 max-w-xl text-4xl font-semibold tracking-[-.04em] sm:text-6xl">Votre vigilance commence ici.</h2><p className="mt-6 max-w-md leading-7 text-muted">Décrivez votre besoin. Notre équipe vous répondra avec une première recommandation adaptée à votre site.</p><div className="mt-8 flex flex-col gap-3 text-sm"><a href="mailto:vigilencesec558@gmail.com" className="text-brand underline underline-offset-4">vigilencesec558@gmail.com</a><a href="tel:+243819174732" className="text-foreground">+243 819 174 732</a><span className="text-muted">Gombe, Kinshasa · RDC</span></div></div><form onSubmit={handleSubmit} className="grid gap-4" aria-label="Formulaire de demande de sécurité"><div className="grid gap-4 sm:grid-cols-2"><label className="grid gap-2 text-sm text-muted">Nom complet<input required name="name" className="border border-border bg-background px-4 py-3 text-foreground outline-none transition-colors placeholder:text-muted focus:border-brand" placeholder="Votre nom" /></label><label className="grid gap-2 text-sm text-muted">Téléphone<input required name="phone" className="border border-border bg-background px-4 py-3 text-foreground outline-none transition-colors placeholder:text-muted focus:border-brand" placeholder="+243 ..." /></label></div><label className="grid gap-2 text-sm text-muted">Type de protection<select name="need" className="border border-border bg-background px-4 py-3 text-foreground outline-none focus:border-brand"><option>Surveillance de site</option><option>Caméras et centre de contrôle</option><option>Protection rapprochée</option><option>Audit de sécurité</option></select></label><label className="grid gap-2 text-sm text-muted">Votre besoin<textarea required name="message" rows={4} className="resize-none border border-border bg-background px-4 py-3 text-foreground outline-none transition-colors placeholder:text-muted focus:border-brand" placeholder="Parlez-nous de votre activité et de votre site..." /></label><div className="flex flex-col gap-3 sm:flex-row sm:items-center"><button type="submit" className="bg-brand px-6 py-3.5 text-sm font-semibold text-background transition-transform hover:-translate-y-0.5">Envoyer la demande</button>{sent && <p role="status" className="text-sm text-signal">Merci. Votre demande a bien été enregistrée.</p>}</div></form></section>

        <footer className="flex flex-col gap-4 border-t border-border py-8 text-xs text-muted sm:flex-row sm:items-center sm:justify-between"><span className="font-mono uppercase tracking-[.18em]">vigilance security</span><span>Protection privée · Réponse locale · Supervision continue</span></footer>
      </div>
    </main>
  )
}
